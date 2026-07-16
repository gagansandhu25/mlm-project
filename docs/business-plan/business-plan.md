---
marp: true
theme: default
paginate: true
size: 16:9
backgroundColor: #fff
style: |
  section {
    font-family: 'Helvetica Neue', Arial, sans-serif;
  }
  h1 { color: #4338ca; }
  h2 { color: #4338ca; }
  table { font-size: 0.85em; }
  section.lead h1 { font-size: 2.4em; }
  .small { font-size: 0.75em; }
---

<!-- _class: lead -->

# [Your Company Name]
## Direct Selling Business Plan

A unilevel direct-selling business built on a custom MLM platform with
automated commissions, real-time genealogy tracking, and a member
self-service portal.

<div class="small">Prepared: June 2026 &middot; Confidential draft &mdash; replace bracketed placeholders before sharing</div>

---

# Executive Summary

- **What**: A direct-selling company distributing [product category]
  through an independent member network, paid via a 10-level unilevel
  commission plan.
- **How**: Members enroll under a sponsor via a unique referral link,
  purchase products, and earn commissions when their referred network
  transacts — calculated and paid automatically by the platform.
- **Why now**: The platform (tree engine, commission engine, wallet,
  member dashboard, admin console) is already built and operating on
  a real database — this plan defines the *business* layered on top
  of it.
- **Ask**: [funding amount / internal sign-off] to fund initial
  inventory, launch marketing, and the first 90 days of operations.

---

# The Opportunity

- Direct selling lets a company scale distribution without a
  traditional sales payroll — members are independent and paid only
  on results.
- A referral-driven model compounds organically: each member's
  network growth is also the company's customer acquisition channel.
- Target gap: **[describe the underserved customer/product need this
  company addresses — fill in for your specific market]**.

**Why a platform-first approach works:** commissions, rank
progression, and payouts are calculated by software — not spreadsheets
— so the plan can scale from 100 to 100,000 members without adding
back-office headcount.

---

# Business Model Overview

```
Company  →  sells products  →  Members  →  refer  →  new Members
   ↑                                                       │
   └──────────── commission paid on downline sales ────────┘
```

- **Revenue**: retail product sales to members and their customers.
- **Cost of distribution**: commissions paid out across up to 10
  unilevel depths, scaled by member rank.
- **Margin**: retail price − commissionable value − cost of goods.
- **Retention engine**: rank progression + residual income from a
  growing downline keeps members active.

---

# Product Line & Pricing

*(Starter catalog — replace with real SKUs before launch)*

| Package | Retail Price | Commissionable Value |
|---|---|---|
| Starter Pack | $99.00 | $99.00 |
| Pro Pack | $299.00 | $299.00 |
| Elite Pack | $999.00 | $999.00 |

> **Margin warning:** in the current seed data, commissionable value
> equals retail price — i.e. 100% of revenue is exposed to commission
> payout. Before launch, set **Commissionable Value meaningfully below
> Retail Price** (e.g. 55–65%) in Admin → Products so the company
> retains a margin after paying the network. This is a one-field
> change per product.

---

# Compensation Plan — Unilevel Overview

- **Plan type**: Unilevel — unlimited width, sponsor-direct placement.
- **Depth paid**: 10 levels (configurable in Admin → Commission Plans).
- **Trigger**: any completed order from a member's downline.
- **Formula per level**:

```
commission = commissionable_value
            × level_percentage
            × rank_multiplier
            (capped per period if a cap is configured)
```

- Inactive/suspended uplines are skipped automatically — commission
  passes through to the next qualifying level's calculation logic
  unaffected (each level is evaluated independently).

---

# Compensation Plan — Level Payout Table

*(default seed configuration — fully editable by admins, no code changes)*

| Level | % of Sale | Level | % of Sale |
|---|---|---|---|
| 1 (direct sponsor) | 10% | 6 | 1% |
| 2 | 5% | 7 | 1% |
| 3 | 3% | 8 | 1% |
| 4 | 2% | 9 | 1% |
| 5 | 2% | 10 | 1% |

**Total payout across all 10 levels: 27% of commissionable value**
(before rank multipliers). Default per-level cap: $5,000/month per
upline — prevents runaway payout from a single high-volume buyer.

---

# Rank Structure & Multipliers

| Rank | Team Volume | Min. Downline | Commission Multiplier |
|---|---|---|---|
| Member | $0 | 0 | 1.00× |
| Bronze | $1,000 | 5 | 1.05× |
| Silver | $5,000 | 20 | 1.20× |
| Gold | $20,000 | 50 | 1.50× |
| Platinum | $50,000 | 100 | 2.00× |

- Rank is evaluated **automatically** every time a commission is
  calculated for that member — no manual review needed.
- Multiplier applies on top of the level percentage, rewarding leaders
  for both volume *and* team size, not volume alone.

---

# Member Journey

1. **Discover** — prospect receives a member's unique referral link.
2. **Enroll** — registers, sponsor and tree placement are recorded
   instantly (materialized-path tree, no manual approval queue).
3. **Purchase** — buys a starter package or product.
4. **Earn** — sponsor and up to 9 further uplines are paid
   automatically the moment the order completes.
5. **Grow** — dashboard shows network, earnings, and rank progress in
   real time, motivating further recruiting.
6. **Withdraw** — requests a payout once above the minimum threshold;
   funds are held pending admin approval.

---

# Technology Platform (already built)

| Capability | Status |
|---|---|
| Genealogy tree (materialized path) | ✅ Live |
| Unilevel commission engine | ✅ Live |
| Binary / Matrix commission engines | 🔜 Planned |
| Member dashboard (network, wallet, rank, referral link) | ✅ Live |
| Wallet ledger + withdrawal requests | ✅ Live (admin approval UI pending) |
| Admin console (users, products, orders, commission config) | ✅ Live |
| Reporting & analytics exports | 🔜 Planned |

This is a genuine operational asset, not a slideware claim — the
commission math, tree placement, and wallet ledger are running
against a real database with automated tests covering the payout
logic.

---

# Go-To-Market Strategy

- **Phase 1 — Founding members**: hand-recruit 20–50 founding members
  with direct outreach; seed the top of the tree deliberately.
- **Phase 2 — Referral flywheel**: founding members' dashboards give
  them a shareable link from day one — every member becomes a
  recruiter automatically.
- **Phase 3 — Rank incentives**: fast-start bonuses for early ranks
  (Bronze in first 30 days) to drive early activation.
- **Channels**: [social media / events / existing customer base —
  fill in for your market].

---

# Revenue Model

For each $1 of retail sale, assuming commissionable value is set to
60% of price (recommended, see margin warning above):

| Line | % of Retail Price |
|---|---|
| Commissionable value | 60% |
| Commission paid out (≤27% × commissionable, avg. rank multiplier ~1.15×) | ~18.6% |
| **Gross margin before COGS** | **~81.4%** |
| Cost of goods (illustrative) | [fill in] |
| **Net contribution margin** | **[calculate]** |

*Adjust the commission and margin assumptions in this table to match
your real product costs before using these numbers externally.*

---

# Financial Projections (illustrative template)

| Month | Active Members | Avg. Order/Member | Monthly Revenue | Est. Commission Payout (18.6%) |
|---|---|---|---|---|
| 1 | 50 | $150 | $7,500 | $1,395 |
| 3 | 200 | $150 | $30,000 | $5,580 |
| 6 | 800 | $150 | $120,000 | $22,320 |
| 12 | 3,000 | $150 | $450,000 | $83,700 |

> **This is a template, not a forecast.** Growth rate, average order
> value, and retention are unknowns until you have real cohort data —
> replace these rows with your own assumptions before presenting to
> investors or lenders.

---

# Risks & Compliance

- **Regulatory**: direct-selling/MLM compensation plans are regulated
  in most jurisdictions (e.g. FTC guidance in the US) — have the
  compensation plan reviewed by counsel before launch.
- **Income representation**: avoid implying guaranteed earnings in any
  marketing material; show realistic average-earnings disclosures.
- **Concentration risk**: cap-per-period (already built) limits payout
  exposure to any single high-volume account.
- **Pyramid-scheme test**: commissions must be tied to genuine retail
  sales to end customers, not purely to recruitment — keep retail
  sales tracked and reportable.

---

# Roadmap & Milestones

| Milestone | Target |
|---|---|
| Finalize product catalog & margins | [date] |
| Compensation plan legal review | [date] |
| Founding member recruitment (50 members) | [date] |
| Admin payout-approval workflow shipped | [date] |
| Public launch | [date] |
| 1,000 active members | [date] |

---

<!-- _class: lead -->

# Next Steps

1. Fill in the bracketed placeholders in this deck with real figures.
2. Set commissionable value below retail price on all products.
3. Get the compensation plan reviewed by legal counsel.
4. Recruit and onboard founding members.
5. Launch.

**Questions / contact:** [name] · [email] · [phone]
