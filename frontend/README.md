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
VITE_PHARMANP_STANDALONE=true
VITE_PHARMANP_AUTH_MODE=token
VITE_PHARMANP_API_TOKEN=
```

The frontend imports the real app from `resources/js`, so module work still lives in:

```text
resources/js/core/
resources/js/modules/
```

Use `npm run frontend:build` only when testing the standalone shell build. The shared-hosting product build remains `npm run build`.
