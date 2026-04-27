# ExtraCash — improvement plan (from system review)

This file tracks what we examined and what we shipped vs what remains.

## Done (this iteration)


| Item                       | Notes                                                                                                                                                           |
| -------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| API rate limits            | Named limiters for login, register, lookups, money routes, checkout, rewards check-in. Relaxed in `testing`.                                                    |
| Idempotent money POSTs     | `Idempotency-Key` header on wallet send, QR pay, checkout, buy-for-me fulfill, single-product buy. Same key + same body replays success; mismatched body → 409. |
| Mobile API base validation | Warns in dev if `EXPO_PUBLIC_API_URL` is missing.                                                                                                               |
| Mobile GET retries         | Limited retries with backoff for GETs on network / 5xx / 408 / 429.                                                                                             |
| Mobile idempotency headers | Auto `Idempotency-Key` on mutating money endpoints if not set.                                                                                                  |
| Tests                      | `WalletTransferApiTest` covers QR pay idempotency replay + 409 on body mismatch.                                                                                |


## Backlog (next iterations)


| Priority | Item                                                                                 |
| -------- | ------------------------------------------------------------------------------------ |
| P0       | HTTPS in production; tighten Android `usesCleartextTraffic` for release builds only. |
| P0       | Structured logging + request id for wallet / checkout / QR flows.                    |
| P1       | API versioning (`/api/v1/...`) before wide client distribution.                      |
| P1       | Reduce enumeration on `/users/lookup` and similar (generic errors + audit).          |
| P1       | Expand Feature tests: checkout, buy-for-me fulfill, idempotency edge cases.          |
| P2       | Redis for cache + rate limiting in production (if not already).                      |
| P2       | N+1 / indexes audit on `products/nearby` and high-traffic list endpoints.            |


## Commands

```bash
# Reward definitions (missions / streak milestones)
composer seed:rewards

# Demo users + listings
composer seed:demo
```

