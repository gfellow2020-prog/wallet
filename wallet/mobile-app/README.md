ExtraCash Mobile (Expo)

Quick start

1. Install dependencies:

   npm install

2. Start the dev server:

   npm run start

Notes

- Set API base URL in `src/api/client.js` to your local Laravel dev server (e.g., `http://10.0.2.2:8000` for Android emulator or `http://localhost:8000` for iOS/simulator when proxied).
- The app UI is intentionally black & white to match the Blade views in the web project.
- Authentication endpoints are expected to be implemented on the Laravel side. See `API INTEGRATION` below.

API INTEGRATION (suggested)

Add API endpoints in Laravel for:
- POST /api/login -> { email, password } returns { token, user }
- POST /api/register -> { name, email, password } returns { token, user }
- GET /api/me -> returns authenticated user and wallet
- GET /api/wallet/transactions -> returns transactions

You can implement token auth using Laravel Sanctum or simple token creation using `createToken()` on the `User` model.
