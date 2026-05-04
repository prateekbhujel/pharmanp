# PharmaNP Frontend Shell

This folder lets a React developer run the PharmaNP SPA without installing PHP, Composer, XAMPP, or Laravel locally.

```bash
npm install
cp frontend/.env.example frontend/.env
npm run frontend:dev
```

Set `VITE_PHARMANP_API_BASE_URL` in `frontend/.env` to the Laravel backend URL. For the live demo backend, use:

```dotenv
VITE_PHARMANP_API_BASE_URL=https://pharmanp.pratikbhujel.com.np
VITE_PHARMANP_APP_BASE_URL=https://pharmanp.pratikbhujel.com.np
VITE_PHARMANP_MEDIA_BASE_URL=https://pharmanp.pratikbhujel.com.np/storage
VITE_PHARMANP_STANDALONE=true
VITE_PHARMANP_AUTH_MODE=token
VITE_PHARMANP_API_TOKEN=
VITE_PHARMANP_ENV=development
VITE_PHARMANP_USE_PROXY=false
VITE_PHARMANP_NTFY_SERVER_URL=
VITE_PHARMANP_NTFY_TOPIC=
```

The blank token is intentional. The frontend login screen calls `/api/v1/auth/login`, receives a PharmaNP bearer token, stores it in browser local storage, and then calls the protected API without Laravel, PHP, Composer, or XAMPP on the frontend developer machine.

Swagger uses the same token. After login, a frontend or mobile developer can copy `localStorage.getItem('pharmanp.api_token')`, open `/api/documentation`, click **Authorize**, paste `Bearer <token>`, and reproduce the same API calls the app is making.

The frontend imports the real app from `resources/js`, so module work still lives in:

```text
resources/js/core/
resources/js/modules/
```

When testing the standalone production build, preview it through Vite:

```bash
npm run frontend:build
npm run frontend:preview
```

Do not open `public/frontend-build/index.html` directly. The shared-hosting product build remains `npm run build`.
