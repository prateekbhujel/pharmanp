# PharmaNP Smart Engine Notes

PharmaNP uses a shared-hosting-safe intelligence layer before any external LLM dependency.

## Current Scope

- Inventory reorder signals
- Slow, steady and fast movement classification
- Overstock detection
- Expiry risk detection
- Product-level recommendations
- OCR provider size preflight

## Runtime Model

The first pass uses normal pharmacy data already inside the application:

- products
- batches
- sales invoices
- sales invoice items

RubixML is used for lightweight clustering when enough product movement rows exist. When there is not enough data, the system falls back to deterministic KPI scoring. This keeps the application reliable on shared hosting and useful on a fresh install.

## What This Is Not

This is not an LLM, chatbot, or AGI system. For PharmaNP, the strongest first layer is explainable operational intelligence:

- what to reorder
- what to avoid reordering
- what is expiring
- what is dead stock
- what is moving fast
- what needs attention today

## OCR Limits

PharmaNP accepts OCR uploads based on `OCR_UPLOAD_MAX_KB`, but OCR.space also has provider limits. The default `.env.example` keeps `OCR_SPACE_MAX_KB=1024` for the free key. With a paid OCR.space key, raise `OCR_SPACE_MAX_KB` according to the plan.

```env
OCR_SPACE_API_KEY=helloworld
OCR_UPLOAD_MAX_KB=10240
OCR_SPACE_MAX_KB=1024
```

The backend blocks oversized provider calls before sending them, so users get a clear message instead of a vague OCR failure.
