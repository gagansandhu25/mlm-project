# Functionality Guide

This document explains what the platform does, end to end: the member-facing
site, the admin console, and the commission engine underneath both. It
describes the system as implemented on this branch.

## What this is

A multi-level-marketing (network marketing) platform: members join under a
sponsor, get placed into a genealogy tree, sell products, and earn
commission both from their own sales and from their downline's sales,
according to whichever compensation plan the business runs (unilevel,
binary, or matrix). Everything is built on Laravel, with a Livewire/Volt
member-facing site and a Filament admin console.

## Tech stack

- **Backend**: Laravel 13, PHP 8.3
- **Member-facing frontend**: Blade + Livewire 3 / Livewire Volt (no separate SPA framework)
- **Admin panel**: Filament 3 (itself built on Livewire)
- **Database**: MySQL in production/dev; the automated test suite runs against an in-memory SQLite database
- **Build**: Vite + Tailwind CSS

## Core concepts

### The genealogy tree

Every member (except the root admin created during install) is placed
under a **sponsor** and, separately, under a **parent** in the tree
structure — these can differ, because *where you're placed* depends on the
active compensation plan's placement rules, while *who sponsored you* is
just "whose referral link/code did you sign up with."

- `users.parent_id` / `users.position` — the actual tree edge (who's directly above you, and, for binary/matrix, which slot).
- `users.sponsor_id` — who referred you, independent of tree placement.
- `users.path` / `users.depth` — a materialized path (`"1/5/12"`) and integer depth, maintained by `TreeService`, used to answer "who are this member's ancestors/descendants" without recursive queries.

`TreeService` (`app/Services/TreeService.php`) is the single place that
reads and writes tree structure. It delegates *where* a new member lands to
a **placement strategy** (`app/Services/Placement/`), chosen by the active
plan type:

- **Unilevel** (`UnilevelPlacementStrategy`) — unlimited width; a recruit always goes directly under their actual sponsor.
- **Binary** (`BinaryPlacementStrategy`) — exactly two children (left/right) per node; new recruits spill over breadth-first to the shallowest open left/right slot.
- **Matrix** (`MatrixPlacementStrategy`) — a configurable fixed width (default 3); recruits spill over breadth-first once a node's children slots are full.

Only **one** plan type is active at a time, controlled by the
`active_plan_type` system setting — chosen during install and intended to
stay fixed afterward (see [Settings](#system-settings), below).

### Orders and commission

An `Order` represents a completed sale. Two dollar figures matter on every
order:

- `amount` — what the customer actually paid (the sale price).
- `commission_value` — the *commissionable* portion of that sale, which all commission math is based on. These are deliberately separate so a business can sell at full retail while only paying commission on, say, 60% of the price (covering COGS/margin) — see `docs/business-plan/business-plan.md` for the reasoning.

The moment an order's `status` is saved as `completed`, `OrderObserver`
fires `CommissionService::calculateForOrder()` synchronously (no queue) —
guarded by an `order.commission_processed` flag so re-saving an already-processed
order never double-pays. This one call does two things for every completed
order, regardless of plan type:

1. Increments the buyer's own `sales_volume` by the order's `commission_value` — this is each member's personal, cumulative sales figure, used for rank qualification and (if enabled) the daily personal volume commission below.
2. Dispatches to whichever network-plan calculator is active (`app/Services/Commission/`):
   - **`UnilevelCommissionCalculator`** / **`MatrixCommissionCalculator`** (both share `LevelBasedCommissionCalculator`) — walks up to a configured max depth (commission levels are configured per-level in `CommissionConfiguration`, seeded 10% → 1% across 10 levels by default), paying each qualifying ancestor a percentage of the order's `commission_value`.
   - **`BinaryCommissionCalculator`** — credits the order's `commission_value` to the correct leg (`left_volume` or `right_volume`) of *every* ancestor up to the root, then pays 10% (configurable) of whichever leg is smaller — the classic "pairing bonus." Volume that doesn't get matched (because the opposite leg is still empty, or a payout cap held some of it back) carries forward and can match on a future order.

Every payout run also:
- Applies the earner's **rank multiplier** (see below) to the base commission amount.
- Respects an optional per-period **cap** (`CommissionConfiguration.cap` + `cap_period`: daily/weekly/monthly) — once an upline has been paid the cap for the period, further commission from that plan type is withheld (and, for binary, the unconsumed volume carries forward rather than being lost).
- Skips any upline whose `status` isn't `active`.
- Credits the earner's wallet immediately and logs an `ActivityLog` entry.
- Re-evaluates the earner's rank (`RankService::evaluate()`).

All of this — pending/paid/cancelled status, base amount before rank
multiplier, the multiplier itself, percentage, level, left/right position —
is recorded as a `Commission` row, viewable in the admin console.

### Daily personal volume commission

Separate from (and running *alongside*) whichever network plan is active:
if `personal_volume_commission_enabled` is on, a scheduled daily job pays
every active member with volume a flat percentage of their **cumulative**
`sales_volume` — not a one-time per-order payout, a recurring daily one, by
design uncapped (the payout grows as their personal volume grows, and
keeps paying indefinitely while the feature is enabled).

This deliberately does **not** write to the `commissions` table — it has
its own `personal_volume_accruals` table (`PersonalVolumeAccrual` model),
because it isn't a network-tree payout (no upline, no level, no left/right
leg) and because a dedicated table lets the schema enforce **one accrual
per user per day** at the database level (`unique(user_id, accrued_on)`) —
a hard guard against ever double-paying if the scheduled job fires twice.

Run via `php artisan commission:personal-volume-daily`
(`PayPersonalVolumeCommission` command → `PersonalVolumeCommissionService`),
scheduled daily in `routes/console.php`. See the
[installation guide](INSTALLATION.md#scheduler) for what needs to be
running in production for this to actually fire.

### Ranks

Five ranks are seeded by default (Member → Bronze → Silver → Gold →
Platinum), each with a minimum team sales volume, a minimum downline
count, and a **commission multiplier** (1.00× up to 2.00×) applied to
every commission type a member earns from — binary pairing bonuses,
level-based unilevel/matrix commissions, and the daily personal volume
accrual all scale by the earner's current rank.

`RankService::evaluate()` runs after every commission payout and
automatically promotes a member the moment their team volume (own +
downline, via `TreeService::getTeamVolume()`) and downline count clear the
next rank's thresholds — there's no manual "promote" step. Rank
qualification criteria are currently the same across all three plan types
(see the open item on binary-specific "balanced leg" qualification in
`TODO.md`).

### Wallet and withdrawals

Every member has one `Wallet` (auto-created on first credit) with a running
`balance`, and every balance change — commission earned, withdrawal
requested — is recorded as an immutable `WalletTransaction` with a
before/after balance snapshot, so the wallet's history is always fully
reconstructable.

Withdrawals (`WithdrawalService::request()`):
- Enforce a configurable minimum (`minimum_payout_threshold`).
- Debit the wallet **immediately** on request (not on approval) so the same balance can't be requested twice while a withdrawal is pending.
- Deduct a configurable percentage fee (`withdrawal_fee_percentage`).
- Land as a `pending` `WithdrawalRequest`, visible to admins in a dedicated dashboard widget (`PendingWithdrawalsWidget`) — there is currently no dedicated admin *resource* for managing withdrawal requests, only the widget.

## Member-facing site (Livewire)

No separate storefront/checkout exists yet — orders are currently only
created through the admin console. The member-facing site covers account
and network visibility:

| Route | Component | What it shows |
|---|---|---|
| `/dashboard` | `Overview` | Personal + team volume, rank progress toward the next rank, wallet balance, at-a-glance stats. |
| `/network` | `Network` | An expandable, accordion-style tree view of your downline — click a node to expand its children; collapsed branches never hit the database. |
| `/genealogy` | `Genealogy` | A drill-down explorer: recenters on whichever member you click into, shows their stats and direct downline, with a breadcrumb trail back up to you. Guarded so you can only ever focus on a genuine descendant of yourself, never an arbitrary member. |
| `/wallet` | `Wallet` | Balance, transaction history, and the withdrawal request form. |
| `/register` | Volt (`pages.auth.register`) | Sign-up gated behind a valid sponsor's referral code; on success, places the new member via `TreeService::placeNewUser()` under the active plan's rules. |
| `/login`, `/forgot-password`, `/reset-password`, `/profile` | Volt / Laravel Breeze-style auth | Standard authentication and profile management. |

All dashboard routes require `auth`, `verified`, and `active` (an
`EnsureUserIsActive` middleware — suspended/inactive members are locked
out of the member area, though still visible/manageable from the admin
side).

## Admin console (Filament, `/admin`)

| Resource / Page | Purpose |
|---|---|
| **Users** | Full member CRUD — role, status, sponsor, rank, tree position/depth (read-only, machine-managed), sales volume, total earnings. |
| **Products** | Catalog: price, the separate commissionable `commission_value`, stock, category, active/inactive status. |
| **Orders** | Create/edit orders; setting `status` to `completed` is what triggers the whole commission engine. `payment_status` is a plain dropdown (pending/completed/failed/refunded). |
| **Commissions** | Every network-plan payout (unilevel/binary/matrix) — read/edit status, amounts, level, position. `plan_type` is hidden here (system-managed) but still stored and drives cap calculations. |
| **Personal Volume Accruals** | The daily personal-volume payouts described above — separate from Commissions by design. |
| **Commission Configurations** | Per-level (or, for binary, single-level) percentage, optional cap, and cap period for each plan type — this is where you tune the actual payout schedule. |
| **Ranks** | Edit the rank ladder: thresholds and commission multipliers. |
| **Wallets** / **Wallet Transactions** | Balance oversight and the full transaction ledger. |
| **System Settings (Advanced)** | Raw key/value editor for every setting — the fallback/escape hatch for anything not covered by the consolidated Settings page below. |
| **Settings** | The primary settings screen — every business-editable setting (company info, active plan + its specific config, personal volume toggle/percentage, payout thresholds) in one form, grouped into sections. `active_plan_type` is locked (disabled) here once the app is installed. |
| **Activity Log** | An append-only audit trail (`ActivityLog::log()`) of significant events — commissions earned, rank promotions, etc. |
| Dashboard widgets | Member growth chart, sales chart, admin stats overview (members, sales, commissions — including personal volume accruals — wallet liability), pending withdrawals. |

## Installation / setup flow

Covered in full in [INSTALLATION.md](INSTALLATION.md). In short: the app
redirects every request to `/install` until an `installed_at` system
setting exists; a guided wizard collects company info, database
credentials, the compensation plan choice (with its plan-specific config),
and the first admin account, then marks the app installed and sends the
new admin to `/admin/login`.
