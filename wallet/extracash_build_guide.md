# ExtraCash — Step-by-Step Build Guide (Dependency Order)

## Project Goal

Build **ExtraCash**, a cashback payment application where users make eligible payments and earn **2% cashback** into their wallet after successful transactions.

This guide is arranged **by dependency order**, so each phase builds on the previous one and avoids rework.

---

# 1. Product Definition and Business Rules

## Objective
Define exactly how the system works before coding.

## Decisions to lock first
- Cashback rate: **2%**
- Cashback applies only to **successful eligible payments**
- Cashback is credited to user wallet
- Cashback should first go into **pending/locked balance**
- Cashback becomes **available after validation/settlement period**
- Cashback is **reversed** if payment is refunded/reversed
- Withdrawals require **KYC** and fraud checks
- Daily/monthly cashback caps may apply
- Certain products/fees/categories may be excluded from cashback

## Deliverables
- Product rules document
- Cashback policy
- Refund/reversal policy
- Wallet usage rules
- Withdrawal rules
- Admin operational rules

## Output checklist
- [ ] Define eligible transaction types
- [ ] Define non-eligible transaction types
- [ ] Define cashback formula
- [ ] Define pending-to-available release period
- [ ] Define withdrawal policy
- [ ] Define refund/reversal logic
- [ ] Define fraud rules and limits
- [ ] Define support and admin adjustment rules

---

# 2. System Architecture and Domain Modeling

## Objective
Design the full system structure before database and code implementation.

## Core modules
- Authentication and User Management
- KYC Module
- Wallet Module
- Payment Module
- Cashback Engine
- Ledger/Accounting Module
- Withdrawal Module
- Refund/Reversal Module
- Fraud/Risk Module
- Notification Module
- Admin Portal
- Reporting and Reconciliation

## Core entities
- User
- Wallet
- Wallet Ledger Entry
- Payment
- Merchant
- Order
- Cashback Transaction
- Withdrawal
- Refund
- KYC Record
- Fraud Flag
- Notification
- Admin Adjustment
- System Setting

## Output checklist
- [ ] Draw module map
- [ ] Define entity relationships
- [ ] Define transaction lifecycle
- [ ] Define payment lifecycle
- [ ] Define cashback lifecycle
- [ ] Define wallet balance lifecycle
- [ ] Define admin control scope

---

# 3. Database Design

## Objective
Design the schema before building application logic.

## Required tables
- users
- user_profiles
- kyc_records
- wallets
- wallet_ledgers
- merchants
- products (optional if needed)
- orders
- payments
- payment_callbacks
- cashback_transactions
- withdrawals
- refunds
- fraud_flags
- notifications
- admin_adjustments
- system_settings
- audit_logs

## Recommended table purpose

### users
Stores account identity and access data.

### user_profiles
Stores profile metadata separate from auth.

### kyc_records
Stores submitted KYC data and verification state.

### wallets
Stores current balances:
- available_balance
- pending_balance
- lifetime_cashback_earned
- lifetime_cashback_spent
- lifetime_withdrawn

### wallet_ledgers
Stores immutable balance movement history.

### merchants
Stores merchant or payment recipient data.

### orders
Stores purchase intent and order-level data.

### payments
Stores actual payment transaction data.

### payment_callbacks
Stores webhook payloads and gateway confirmations.

### cashback_transactions
Stores cashback earning records and status.

### withdrawals
Stores user withdrawal requests and lifecycle.

### refunds
Stores refund/reversal records.

### fraud_flags
Stores suspicious activity markers and review notes.

### admin_adjustments
Stores manual balance corrections or interventions.

### system_settings
Stores configurable rules like cashback %, caps, lock period.

### audit_logs
Stores critical user/admin actions for traceability.

## Output checklist
- [ ] Design normalized schema
- [ ] Add foreign keys
- [ ] Add indexes for transaction lookup
- [ ] Add unique constraints for idempotency
- [ ] Add soft deletes where needed
- [ ] Add audit columns
- [ ] Add status enums or controlled values
- [ ] Add money-safe decimal columns

---

# 4. Status and Lifecycle Definitions

## Objective
Define all statuses before writing service logic.

## Payment statuses
- initiated
- pending
- processing
- successful
- failed
- reversed
- cancelled

## Cashback statuses
- pending
- locked
- available
- reversed
- expired

## Withdrawal statuses
- requested
- under_review
- approved
- processing
- paid
- rejected
- failed
- reversed

## Refund statuses
- requested
- approved
- processed
- failed

## KYC statuses
- not_submitted
- pending
- verified
- rejected
- expired

## Fraud statuses
- clear
- flagged
- under_review
- blocked
- resolved

## Output checklist
- [ ] Define state transitions
- [ ] Prevent invalid transitions
- [ ] Document lifecycle diagrams
- [ ] Reuse constants/enums in codebase

---

# 5. Authentication and User Foundation

## Objective
Build user access before money-related features.

## Features
- User registration
- Login
- Password reset
- OTP/phone verification
- Email verification if needed
- Session/token handling
- Account status enforcement

## Required outputs
- Auth endpoints
- Login/register screens
- User profile management
- Verified contact enforcement

## Dependency notes
This phase must be complete before:
- Wallet creation
- KYC onboarding
- Payment processing
- Cashback issuance

## Output checklist
- [ ] Register user
- [ ] Verify phone/email
- [ ] Login securely
- [ ] Logout and session revoke
- [ ] Restrict suspended users
- [ ] Auto-create profile shell

---

# 6. Wallet Foundation

## Objective
Create the user wallet system before cashback or withdrawals.

## Features
- One wallet per user
- Available balance
- Pending balance
- Totals tracking
- Wallet summary endpoint
- Wallet transaction history

## Rules
- Never update balances without ledger records
- Wallet must be created automatically for each valid user
- Wallet balance changes must be atomic

## Output checklist
- [ ] Auto-create wallet on user creation
- [ ] Build wallet summary API
- [ ] Build transaction history API
- [ ] Enforce one-wallet-per-user rule
- [ ] Add balance integrity checks

---

# 7. Ledger System (Critical Dependency)

## Objective
Implement immutable accounting before cashback, withdrawals, and refunds.

## Why this is critical
The ledger is the source of truth for all money movement.

## Ledger entry types
- payment_debit
- cashback_credit_pending
- cashback_release_to_available
- cashback_debit_spend
- cashback_reversal
- withdrawal_debit
- withdrawal_reversal
- admin_adjustment_credit
- admin_adjustment_debit

## Required fields
- user_id
- wallet_id
- type
- direction
- amount
- reference_type
- reference_id
- balance_before
- balance_after
- metadata
- created_at

## Rules
- Ledger entries must be append-only
- No silent balance edits
- All wallet adjustments must leave audit trail
- Use DB transactions for wallet + ledger updates

## Output checklist
- [ ] Create ledger schema
- [ ] Build ledger service
- [ ] Make balance update atomic
- [ ] Add integrity validation job
- [ ] Block direct wallet mutation outside service layer

---

# 8. KYC and Compliance Layer

## Objective
Prevent abuse before enabling withdrawals and advanced money actions.

## Features
- KYC submission form
- ID upload/document handling
- Verification status
- Manual/admin review
- KYC-required gate for withdrawals

## Rules
- Payment may work pre-KYC depending on your policy
- Withdrawal must be blocked until KYC verified
- Suspicious accounts can be restricted

## Output checklist
- [ ] Build KYC form
- [ ] Store verification status
- [ ] Add admin review interface
- [ ] Restrict withdrawals for non-verified users
- [ ] Log review decisions

---

# 9. Merchant / Product / Order Layer

## Objective
Represent what the user is paying for before processing payments.

## Features
- Merchant records
- Product or service definition if needed
- Order creation
- Order pricing and amount breakdown
- Eligible amount calculation

## Important logic
Cashback should be based on **eligible_amount**, not blindly on gross total.

## Example
If total is:
- Product = K500
- Fees = K20

Eligible cashback may be calculated only on **K500**, not K520.

## Output checklist
- [ ] Create merchant model
- [ ] Create order model
- [ ] Store gross amount and eligible amount separately
- [ ] Store fee/tax breakdown
- [ ] Link order to user and merchant

---

# 10. Payment Module

## Objective
Process user payments before issuing cashback.

## Features
- Initiate payment
- Generate payment reference
- Store payment intent
- Redirect or collect payment
- Receive gateway callback/webhook
- Reconcile payment status

## Rules
- Never mark payment successful from frontend only
- Use provider confirmation/webhook
- Verify callback signatures
- Store raw callback payloads
- Enforce idempotent callback handling

## Required fields
- payment_reference
- provider_reference
- user_id
- order_id
- amount
- eligible_amount
- payment_method
- currency
- status
- paid_at

## Output checklist
- [ ] Create payment initiation endpoint
- [ ] Generate unique payment reference
- [ ] Store payment intent
- [ ] Build callback handler
- [ ] Verify payment authenticity
- [ ] Update payment status safely
- [ ] Prevent duplicate success processing

---

# 11. Cashback Engine

## Objective
Issue cashback only after valid successful payment flow is stable.

## Trigger
Cashback must be generated only when:
- payment status becomes successful
- transaction is eligible
- cashback not already issued for that payment

## Cashback formula
```text
cashback = eligible_amount × 0.02
```

## Rules
- One cashback record per valid payment
- Use idempotency checks
- Create cashback in pending/locked state first
- Link cashback to payment and user
- Write ledger entry immediately

## Recommended flow
1. Payment marked successful
2. Cashback engine checks eligibility
3. System computes cashback
4. Cashback record created as pending/locked
5. Wallet pending balance increases
6. Ledger entry created
7. Notification sent

## Output checklist
- [ ] Build cashback service
- [ ] Add eligibility validator
- [ ] Add idempotency check
- [ ] Create cashback transaction record
- [ ] Update wallet pending balance
- [ ] Write ledger entry
- [ ] Notify user

---

# 12. Cashback Settlement / Release Logic

## Objective
Move cashback from pending to available after lock/validation period.

## Why needed
This reduces refund abuse and fake transaction abuse.

## Example flow
- Cashback earned today
- Held for 7 days
- Released on day 8 if no refund/reversal/fraud block exists

## Required logic
- Scheduled job / queue worker
- Check cashback status
- Check hold period elapsed
- Check no refund/reversal/fraud issue
- Move from pending to available
- Write ledger entry

## Output checklist
- [ ] Create scheduled settlement job
- [ ] Move cashback from pending to available
- [ ] Update wallet balances correctly
- [ ] Log release in ledger
- [ ] Skip blocked/fraud/refunded payments

---

# 13. Refund and Reversal Logic

## Objective
Reverse cashback and correct balances when original payment is invalidated.

## Scenarios
- Payment refunded
- Payment chargeback
- Payment reversed by provider
- Order cancelled after settlement

## Rules
- Refund event must find linked cashback transaction
- If cashback is pending, reverse pending balance
- If cashback is already available, deduct available balance
- If already spent, create negative recoverable balance or recovery flow

## Output checklist
- [ ] Build refund record handling
- [ ] Link refund to payment
- [ ] Reverse related cashback
- [ ] Update wallet safely
- [ ] Write reversal ledger entry
- [ ] Prevent multiple reversals for same cashback

---

# 14. Withdrawal Module

## Objective
Allow user cash-out only after wallet, KYC, and fraud controls exist.

## Features
- Withdrawal request
- Balance check
- KYC verification gate
- Fraud/risk checks
- Admin approval or automated approval
- Payout integration
- Withdrawal history

## Rules
- Withdraw only from available balance
- Minimum withdrawal amount may apply
- Daily withdrawal limit may apply
- Withdrawal should reserve/debit balance safely
- Failed payout should restore funds correctly

## Output checklist
- [ ] Build withdrawal request flow
- [ ] Check available balance
- [ ] Check KYC status
- [ ] Flag risky withdrawals
- [ ] Add admin approval flow
- [ ] Add payout execution
- [ ] Add failure rollback handling

---

# 15. Fraud and Risk Controls

## Objective
Protect the cashback model from abuse.

## Controls
- Duplicate account detection
- Device fingerprint review if available
- Same phone/payment instrument abuse checks
- Velocity limits
- Repeated refund pattern checks
- Same-value repeated purchase detection
- Merchant collusion checks
- Suspicious withdrawal patterns

## Actions
- Flag account
- Freeze cashback release
- Block withdrawal
- Send for manual review
- Suspend account

## Output checklist
- [ ] Create fraud rules engine
- [ ] Create fraud flag records
- [ ] Add review queue for admins
- [ ] Integrate fraud checks into cashback release and withdrawal flows

---

# 16. Notifications Module

## Objective
Keep users informed across all money events.

## Events
- Registration success
- Phone/email verification
- Payment success
- Cashback earned
- Cashback released
- Cashback reversed
- Withdrawal requested
- Withdrawal approved/rejected
- KYC approved/rejected

## Channels
- In-app
- SMS
- Email
- Push notification

## Output checklist
- [ ] Create notification event map
- [ ] Queue notifications
- [ ] Store delivery status
- [ ] Add notification center UI

---

# 17. Admin Portal

## Objective
Give operations team full control and visibility.

## Admin features
- User management
- KYC review
- Payment monitoring
- Cashback monitoring
- Refund monitoring
- Withdrawal approvals
- Fraud review queue
- Manual wallet adjustments
- System settings
- Audit log viewer
- Reports dashboard

## Output checklist
- [ ] Create secure admin auth and permissions
- [ ] Build user management
- [ ] Build KYC review tools
- [ ] Build withdrawal review tools
- [ ] Build fraud investigation tools
- [ ] Build system setting controls
- [ ] Build audit log interface

---

# 18. Reporting and Reconciliation

## Objective
Ensure financial correctness and business visibility.

## Reports
- Total payments
- Total successful payments
- Total cashback issued
- Total cashback pending
- Total cashback released
- Total cashback reversed
- Total withdrawals requested
- Total withdrawals paid
- Wallet liability report
- Fraud incident report
- Merchant performance report

## Reconciliation tasks
- Gateway payments vs internal payment records
- Wallet balances vs ledger totals
- Cashback totals vs payment eligibility totals
- Withdrawal payouts vs withdrawal records

## Output checklist
- [ ] Create financial reports
- [ ] Create reconciliation jobs
- [ ] Create discrepancy alerts
- [ ] Create exportable reports

---

# 19. Security Hardening

## Objective
Protect user money and transaction integrity.

## Security requirements
- Encrypt sensitive data
- Hash passwords securely
- Verify webhook signatures
- Use RBAC for admin access
- Rate limit sensitive endpoints
- Add IP/device anomaly checks
- Log admin actions
- Protect file uploads
- Use secure secret storage
- Enforce HTTPS

## Output checklist
- [ ] Implement secure auth practices
- [ ] Protect money movement endpoints
- [ ] Add rate limits
- [ ] Add audit logging
- [ ] Secure webhook endpoints
- [ ] Secure document uploads

---

# 20. Testing Strategy

## Objective
Validate correctness before production launch.

## Test layers
- Unit tests
- Feature/integration tests
- Payment callback tests
- Cashback idempotency tests
- Refund reversal tests
- Withdrawal validation tests
- Ledger integrity tests
- Fraud rule tests
- Admin permission tests

## Critical scenarios to test
- Successful payment creates one cashback only
- Duplicate webhook does not duplicate cashback
- Refunded payment reverses cashback
- Cashback release job moves correct amounts
- Failed withdrawal restores funds correctly
- Suspended user cannot transact
- Unverified KYC user cannot withdraw

## Output checklist
- [ ] Write service tests
- [ ] Write end-to-end flow tests
- [ ] Write idempotency tests
- [ ] Write reconciliation tests
- [ ] Write security tests

---

# 21. Deployment and Operations

## Objective
Prepare production-ready release.

## Requirements
- Environment variable setup
- Queue workers
- Scheduled jobs
- Monitoring
- Error tracking
- Database backups
- Transaction log retention
- Admin alerting
- Rollback plan

## Scheduled jobs likely needed
- Cashback release scheduler
- Reconciliation jobs
- Expired cashback handler
- Fraud review reminders
- Notification retries

## Output checklist
- [ ] Configure queues
- [ ] Configure scheduler/cron
- [ ] Configure backups
- [ ] Configure monitoring
- [ ] Configure alerts
- [ ] Prepare rollback procedure

---

# 22. Recommended Build Order Summary

Follow this order strictly:

1. Product rules and policy definition
2. Architecture and domain modeling
3. Database schema design
4. Status/lifecycle definitions
5. Authentication and user foundation
6. Wallet foundation
7. Ledger system
8. KYC/compliance layer
9. Merchant/product/order layer
10. Payment module
11. Cashback engine
12. Cashback settlement/release
13. Refund and reversal logic
14. Withdrawal module
15. Fraud and risk controls
16. Notifications
17. Admin portal
18. Reporting and reconciliation
19. Security hardening
20. Testing
21. Deployment and operations

---

# 23. Recommended MVP Scope

For version 1, build only:
- User registration/login
- Wallet creation
- Payment processing
- 2% cashback earning
- Pending cashback
- Cashback release after hold period
- Basic withdrawal request
- KYC verification
- Admin review
- Transaction history
- Basic reporting

Do not start with:
- advanced gamification
- referrals
- loyalty tiers
- merchant promotions
- complex automation rules
- multi-currency
- advanced analytics

---

# 24. Engineering Best Practices

- Use service classes for business logic
- Use database transactions for money updates
- Use immutable ledger records
- Use idempotency for webhooks and cashback issuance
- Never trust frontend for payment success
- Keep balance logic server-side only
- Prefer queued jobs for settlement and notifications
- Audit every admin money action
- Separate available vs pending funds
- Write tests before production release

---

# 25. Phase-by-Phase Handover Prompts for Development

## Phase 1 Completion Criteria
- Product rules approved
- Cashback policy finalized
- Fraud and refund policy finalized

## Phase 2 Completion Criteria
- System architecture documented
- Entity relationships mapped
- Lifecycle transitions approved

## Phase 3 Completion Criteria
- Database schema ready
- Migrations planned
- Constraints and indexes defined

## Phase 4+ Completion Criteria
Each phase should only begin after the previous dependency phase is stable.

---

# 26. Final Build Principle

For ExtraCash, always build around this principle:

**Payment confirmed -> cashback created once -> wallet updated via ledger -> cashback released after validation -> refunds reverse cashback -> withdrawals require KYC and fraud checks**

This should be the backbone of the system.

