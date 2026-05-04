export const learningPaths = [
    {
        key: 'intern',
        title: 'Intern: first useful week',
        promise: 'You will understand the app shape, run it, read one page, make a harmless UI change, and verify it without breaking backend contracts.',
        minutes: 90,
        lessons: ['workspace-map', 'react-basics', 'server-table-crud', 'git-sparse', 'verify-before-push'],
    },
    {
        key: 'frontend',
        title: 'Frontend developer',
        promise: 'You will build React pages against Swagger/JWT without needing XAMPP, while still respecting Laravel response contracts.',
        minutes: 150,
        lessons: ['frontend-shell', 'react-basics', 'server-table-crud', 'forms-and-validation', 'jwt-swagger'],
    },
    {
        key: 'backend',
        title: 'Backend developer',
        promise: 'You will add module APIs with Request, DTO, Service, Repository Interface, Repository, Resource, Swagger examples, and tests.',
        minutes: 180,
        lessons: ['laravel-module-flow', 'repository-pattern', 'dto-resource-contract', 'jwt-swagger', 'module-doctor'],
    },
    {
        key: 'fullstack',
        title: 'Full-stack owner',
        promise: 'You will ship one business workflow from schema to Swagger to React table/form/report without mixing responsibilities.',
        minutes: 240,
        lessons: ['feature-slice', 'server-table-crud', 'forms-and-validation', 'transaction-workflow', 'verify-before-push'],
    },
    {
        key: 'senior',
        title: 'Senior reviewer',
        promise: 'You will review whether a change preserves module boundaries, tenant scope, stock/accounting safety, performance and developer clarity.',
        minutes: 120,
        lessons: ['architecture-review', 'transaction-workflow', 'large-data-thinking', 'module-doctor', 'verify-before-push'],
    },
];

export const lessons = [
    {
        key: 'workspace-map',
        title: 'How to read this codebase without panic',
        audience: 'Everyone',
        outcome: 'You can open the repo and know where frontend, backend, routes, services, repositories, tests and Swagger docs live.',
        mentalModel: [
            'PharmaNP is one Laravel repo shipping one React SPA, but the frontend can run as a standalone Vite shell.',
            'Laravel owns API, auth, validation, database, accounting, stock posting and Swagger.',
            'React owns screens, table filters, transaction forms, validation display and user flow.',
            'Modules own business boundaries. Core owns shared conventions. Do not put module-specific behavior in random global helpers.',
        ],
        walkThrough: [
            'Start at resources/js/core/layout/AppShell.jsx to understand navigation, auth user, breadcrumbs and active route selection.',
            'Open resources/js/core/modules/routeRegistry.js or resources/js/core/modules registration if you need to find which page renders for a URL.',
            'For backend, open config/pharmanp-modules.php first. It tells you which modules exist and which provider owns routes.',
            'Open a module provider. It binds repository interfaces and loads Routes/api.php through the shared module provider.',
            'Open the controller only after reading the request and service. Controllers should be boring.',
        ],
        example: `URL: /app/inventory/products
React route -> ProductsPage.jsx
Endpoint key -> endpoints.products
API route -> app/Modules/Inventory/Routes/api.php
Controller -> ProductController
Request -> ProductIndexRequest / ProductStoreRequest
DTO -> ProductData
Service -> ProductService
Repository Interface -> ProductRepositoryInterface
Repository -> ProductRepository
Resource -> ProductResource`,
        practice: [
            'Find the Products page route and write down the backend route it calls.',
            'Find one request class and identify one rule that protects database integrity.',
            'Find one resource class and identify the exact JSON fields the frontend is allowed to depend on.',
        ],
        mistakes: [
            'Starting with the database table and guessing the frontend shape.',
            'Changing a resource field name without searching every frontend use.',
            'Adding a shortcut endpoint because it is faster today.',
        ],
    },
    {
        key: 'frontend-shell',
        title: 'Frontend-only setup that does not scare React developers',
        audience: 'Frontend',
        outcome: 'You can run the React shell without PHP/XAMPP and point it to the shared backend API.',
        mentalModel: [
            'The frontend shell is not a second product. It is a developer shell for resources/js inside the same repo.',
            'Vite environment variables define which API the React app talks to.',
            'The API contract comes from Swagger and the response envelope, not from reading private Laravel code.',
        ],
        walkThrough: [
            'Clone sparse when you only need frontend files. Use --skip-checks when package.json is selected because sparse-checkout normally expects directories.',
            'Copy frontend/.env.example to frontend/.env and set VITE_PHARMANP_API_BASE_URL to the backend server.',
            'Run npm run frontend:dev for development. Do not open frontend/index.html directly.',
            'Run npm run frontend:build and npm run frontend:preview before handoff.',
            'Login normally. The JWT is stored in localStorage under pharmanp.api_token.',
        ],
        example: `git clone --filter=blob:none --sparse https://github.com/prateekbhujel/pharmanp.git pharmanp-frontend
cd pharmanp-frontend
git sparse-checkout set --skip-checks frontend resources/js resources/css package.json package-lock.json vite.config.js
cp frontend/.env.example frontend/.env
npm install
npm run frontend:dev`,
        practice: [
            'Run the frontend shell and sign in.',
            'Open browser storage and find pharmanp.api_token.',
            'Change one UI label in a module page and verify the app does not full-page reload.',
        ],
        mistakes: [
            'Using CRA-style REACT_APP variables. This app uses VITE_ variables.',
            'Opening the built index file directly and thinking the router is broken.',
            'Hardcoding localhost in a module file instead of using endpoints.js.',
        ],
    },
    {
        key: 'react-basics',
        title: 'React from zero to PharmaNP pages',
        audience: 'Frontend / Intern',
        outcome: 'You understand enough React to read a PharmaNP CRUD page and modify it safely.',
        mentalModel: [
            'A React component is a function that returns UI for the current state.',
            'State is memory inside the component. Changing state re-renders the UI.',
            'Effects run side work such as loading data after render.',
            'Forms are controlled by Ant Design Form. Laravel validation errors must be displayed back into the same form.',
        ],
        walkThrough: [
            'Read a simple page top to bottom. Imports show dependencies. Hooks show state. Columns show table shape. Submit handlers show API writes.',
            'Do not create one giant app.jsx. Each business area stays in resources/js/modules/<module>.',
            'Use shared components when possible: ServerTable, FormDrawer/FormModal patterns, StatusTag, Money, DateText, ConfirmDelete.',
            'Keep API calls in page/service boundary. Do not scatter axios calls inside random render helpers.',
        ],
        example: `function ProductNameCell({ product }) {
  return (
    <div>
      <strong>{product.name}</strong>
      <span>{product.product_code}</span>
    </div>
  );
}

// This is enough React to start:
// props come in, JSX goes out, state changes re-render the page.`,
        practice: [
            'Create a tiny ProductNameCell component inside ProductsPage and use it in one table column.',
            'Move only presentation logic. Do not move API calls into this small component.',
            'Run npm run build after the change.',
        ],
        mistakes: [
            'Putting business calculations in render code.',
            'Copying a table page and forgetting to change endpoint keys.',
            'Using array indexes as row keys when database IDs exist.',
        ],
    },
    {
        key: 'server-table-crud',
        title: 'From normal CRUD to PharmaNP CRUD',
        audience: 'Frontend / Full-stack',
        outcome: 'You can build a list page with filters, server-side pagination, sorting, create/edit modal or drawer, validation errors, and soft delete behavior.',
        mentalModel: [
            'A student CRUD loads everything. PharmaNP never does that.',
            'Every list sends page, per_page, search, sort_field, sort_order and filters to the backend.',
            'Create/edit form size decides UI: small modal, medium drawer, transaction full page.',
            'The API resource decides response fields. The table does not guess database columns.',
        ],
        walkThrough: [
            'Add endpoint key in resources/js/core/api/endpoints.js.',
            'Use useServerTable with default page size 15 and indexed backend filters.',
            'Define columns with stable widths, renderers, status tags and action icons.',
            'On create/update, call POST/PUT, show notification, close form, reload table.',
            'On delete, use ConfirmDelete and soft delete where business data should remain auditable.',
        ],
        example: `const table = useServerTable({ endpoint: endpoints.products });

<ServerTable
  rowKey="id"
  columns={columns}
  dataSource={table.rows}
  loading={table.loading}
  pagination={table.pagination}
  onChange={table.handleTableChange}
/>`,
        practice: [
            'Find one page that uses useServerTable and identify all query params it sends.',
            'Add one harmless filter to the UI and backend request class.',
            'Verify the page still works when page size changes from 15 to 10.',
        ],
        mistakes: [
            'Filtering in React after fetching one page.',
            'Adding filters that are not indexed or cannot be searched efficiently.',
            'Building edit forms directly inside table rows.',
        ],
    },
    {
        key: 'forms-and-validation',
        title: 'Forms, validation, modal, drawer and full-page decisions',
        audience: 'Frontend / Full-stack',
        outcome: 'You can decide the right UI surface and wire Laravel validation without messy duplicate state.',
        mentalModel: [
            'A form is not just fields. It is a workflow and error recovery surface.',
            'Small setup records use modal. Medium master records use drawer or wide modal. Transaction documents use full page.',
            'Laravel FormRequest is the source of validation truth. React mirrors errors, it does not invent a second business rule set.',
        ],
        walkThrough: [
            'Use Ant Form for field state and validation display.',
            'On API 422, map errors into form.setFields.',
            'Keep quick-add dropdowns local and reusable. A quick-add must refresh options after save.',
            'Date fields must accept typed values when used by accountants and sales operators.',
        ],
        example: `try {
  await http.post(endpoints.products, form.getFieldsValue());
  notification.success({ message: 'Product saved' });
} catch (error) {
  form.setFields(toFormErrors(validationErrors(error)));
}`,
        practice: [
            'Open ProductsPage and find where validation errors enter the form.',
            'Find one quick-add dropdown and trace how options refresh after create.',
            'Explain why purchase bill entry should not be a small modal.',
        ],
        mistakes: [
            'Showing validation only as toast and losing field-level context.',
            'Putting purchase/sales line item repeaters inside cramped modals.',
            'Duplicating validation rules in React and Laravel until they disagree.',
        ],
    },
    {
        key: 'laravel-module-flow',
        title: 'Laravel modular flow like PIS, adapted for PharmaNP',
        audience: 'Backend',
        outcome: 'You can add a backend feature using the module pattern without controller bloat.',
        mentalModel: [
            'Provider binds interfaces. Controller asks service. Service asks repository contract. Repository owns reusable query/persistence detail.',
            'Requests validate. DTOs normalize payloads. Resources shape output. Services own transactions.',
            'This is still a modular monolith, not microservices. Module boundaries are code boundaries, not deployment boundaries.',
        ],
        walkThrough: [
            'Create or update the module provider binding: Interface::class to Repository::class.',
            'Put route definitions in the module Routes/api.php.',
            'Keep controller methods short: request, service call, resource/envelope response.',
            'Use DTO when the payload is reused or has meaningful business structure.',
            'Write tests around calculations and transaction side effects.',
        ],
        example: `class ProductController extends ModularController
{
    public function store(ProductStoreRequest $request, ProductService $service): JsonResponse
    {
        $product = $service->create(ProductData::fromArray($request->validated()), $request->user());

        return $this->resource(new ProductResource($product), 'Product created successfully.', 201);
    }
}`,
        practice: [
            'Open InventoryServiceProvider and find ProductRepositoryInterface binding.',
            'Open ProductController and confirm it does not build complex queries inline.',
            'Open ProductService and identify the transaction or business rule boundary.',
        ],
        mistakes: [
            'Injecting Eloquent models everywhere and calling it modular.',
            'Adding private helper methods in controllers that really belong in services.',
            'Returning raw models when a Resource exists.',
        ],
    },
    {
        key: 'repository-pattern',
        title: 'Repository pattern without ceremony',
        audience: 'Backend / Senior',
        outcome: 'You can decide what belongs in repository, service, DTO or resource.',
        mentalModel: [
            'Repository hides reusable query and persistence details. It does not decide business policy.',
            'Service decides workflow and transaction. Repository gives it reliable data operations.',
            'Interface exists so service depends on contract, provider binds implementation.',
        ],
        walkThrough: [
            'Put table query sorting/search/select logic in repository when reused or complex.',
            'Keep one-off tiny queries in service only if adding a repository method would be noise.',
            'Never let repository call UI concepts, request objects or notifications.',
            'Never let controller assemble transaction steps.',
        ],
        example: `interface ProductRepositoryInterface
{
    public function paginate(TableQueryData $table): LengthAwarePaginator;
    public function create(array $data): Product;
    public function update(Product $product, array $data): Product;
}`,
        practice: [
            'Find one repository method and explain which service uses it.',
            'Find one service method and explain why it is not in the controller.',
            'Run php artisan pharmanp:module-doctor --openapi after changing bindings.',
        ],
        mistakes: [
            'Putting every single query behind a pointless repository method.',
            'Putting accounting posting logic inside purchase repository.',
            'Creating interfaces but forgetting provider bindings.',
        ],
    },
    {
        key: 'dto-resource-contract',
        title: 'DTOs, Requests and Resources: the API contract',
        audience: 'Backend / Full-stack',
        outcome: 'You can keep input, business data and output separate so frontend changes do not corrupt domain logic.',
        mentalModel: [
            'Request validates outside world input.',
            'DTO converts validated arrays into predictable business data.',
            'Resource converts model/domain output into frontend-safe JSON.',
            'The response envelope wraps everything consistently for React and Swagger.',
        ],
        walkThrough: [
            'Add validation in Http/Requests first.',
            'Create DTO only when payload is meaningful enough to deserve a named shape.',
            'Return Resource or ResourceCollection so field names stay stable.',
            'Keep envelope shape: status, code, message, data, links, meta, errors.',
        ],
        example: `{
  "status": "success",
  "code": 200,
  "message": "Products retrieved successfully.",
  "data": [],
  "links": {},
  "meta": { "current_page": 1, "per_page": 15, "total": 0 }
}`,
        practice: [
            'Open a Resource and identify one frontend column depending on it.',
            'Open a Request and identify one exists-style validation handled without database FK constraints.',
            'Use Swagger to confirm response examples before wiring React.',
        ],
        mistakes: [
            'Letting frontend depend on accidental Eloquent JSON.',
            'Returning different error shapes from different controllers.',
            'Skipping DTO then passing random arrays through three services.',
        ],
    },
    {
        key: 'jwt-swagger',
        title: 'JWT, Swagger and frontend debugging',
        audience: 'Frontend / Backend / Mobile',
        outcome: 'You can login once, inspect the bearer token, test the same API in Swagger, and debug frontend issues from real responses.',
        mentalModel: [
            'JWT is the single auth style for SPA, frontend shell, Swagger and future mobile app.',
            'The frontend stores the token in localStorage as pharmanp.api_token.',
            'Swagger Authorize expects the raw bearer token value. If it asks for full value, use Bearer <token>.',
            'A 401 means token missing, expired, invalid, revoked or hitting an endpoint outside expected middleware.',
        ],
        walkThrough: [
            'Login through the app.',
            'Open browser console and run localStorage.getItem("pharmanp.api_token").',
            'Open /api/documentation and click Authorize.',
            'Paste token and test /api/v1/me.',
            'If frontend table says unauthenticated, compare the exact request in Swagger with the same token.',
        ],
        example: `// Browser console
localStorage.getItem('pharmanp.api_token')

// Curl shape
curl -H "Authorization: Bearer <token>" \\
     -H "Accept: application/json" \\
     https://pharmanp.pratikbhujel.com.np/api/v1/me`,
        practice: [
            'Copy your own token after login and call /api/v1/me in Swagger.',
            'Clear localStorage token and confirm the app returns to login.',
            'Login again and confirm tables send Authorization header.',
        ],
        mistakes: [
            'Mixing old session auth assumptions with JWT-only endpoints.',
            'Sharing a token in chat without understanding it grants account access until expiry/revocation.',
            'Debugging React state before checking the API response in Swagger.',
        ],
    },
    {
        key: 'feature-slice',
        title: 'How to build one real PharmaNP feature',
        audience: 'Full-stack',
        outcome: 'You can add a new module workflow without touching ten unrelated files randomly.',
        mentalModel: [
            'A feature is a vertical slice: database, backend contract, service logic, Swagger, frontend page and tests.',
            'Do not start with UI if the transaction/accounting/stock behavior is unclear.',
            'Do not start with database if the business vocabulary is unclear.',
        ],
        walkThrough: [
            'Write the business sentence first: who does what, what data changes, what reports depend on it.',
            'Design table columns and indexes for filters. No DB-level foreign keys by project policy.',
            'Add request, DTO, service, repository, resource and route.',
            'Add Swagger annotation with request and response examples.',
            'Add React endpoint, page route, table, form, validation and action buttons.',
            'Add tests and run module doctor.',
        ],
        example: `Feature: Purchase expiry return
Business: supplier receives expired/near-expired stock back.
Stock: batch quantity decreases.
Accounting: supplier payable/adjustment changes.
Reports: expiry return report and supplier aging reflect the transaction.
Frontend: list page + full-page return form + printable document.`,
        practice: [
            'Pick one existing workflow and write its business sentence.',
            'List every table/report/accounting effect before opening the editor.',
            'Find the service that owns the transaction boundary.',
        ],
        mistakes: [
            'Making a page that saves data but does not update reports.',
            'Duplicating accounting logic inside sales and purchase controllers.',
            'Creating fake report tables disconnected from source transactions.',
        ],
    },
    {
        key: 'transaction-workflow',
        title: 'Stock and accounting transaction safety',
        audience: 'Backend / Senior',
        outcome: 'You can review purchase, sales, returns, payments and vouchers without allowing silent data corruption.',
        mentalModel: [
            'Stock and accounting writes are not CRUD. They are financial events.',
            'Purchase increases stock and payable. Sales decreases stock and creates receivable or payment.',
            'Payment In/Out settle receivables/payables and must affect aging and ledger behavior.',
            'Manual vouchers stay because not every accounting adjustment comes from sales or purchase.',
        ],
        walkThrough: [
            'Find the service method handling the write.',
            'Confirm DB::transaction wraps all stock/accounting changes.',
            'Confirm accounting posting uses shared services, not duplicated local debit/credit logic.',
            'Confirm due amount, paid amount and aging report source data are updated.',
            'Confirm print/export/report reads the same source transaction data.',
        ],
        example: `Sales Bill:
1. Validate customer, items, batches and payment mode.
2. Create invoice and invoice items.
3. Deduct batch/product stock.
4. Post receivable/payment accounting.
5. Return resource with printable URL.
All five steps belong in one transaction service.`,
        practice: [
            'Open SalesInvoiceService and find stock deduction.',
            'Open PaymentSettlementService and find how receivable/payable gets settled.',
            'Write one test that proves a failed stock write does not leave a half-created invoice.',
        ],
        mistakes: [
            'Using controller code for stock movement.',
            'Rounding accounting totals for display before storing precise values.',
            'Treating Payment In/Out as buttons instead of ledger-connected workflows.',
        ],
    },
    {
        key: 'module-doctor',
        title: 'Module doctor command and architecture checks',
        audience: 'Backend / Senior',
        outcome: 'You can check whether modules still satisfy PharmaNP’s modular contract.',
        mentalModel: [
            'The doctor command is not a replacement for code review. It catches structural drift.',
            'It checks module folders, routes, repository contracts, service-provider bindings and OpenAPI annotation coverage.',
            'Run it before asking someone else to review a modular refactor.',
        ],
        walkThrough: [
            'Run php artisan pharmanp:module-doctor.',
            'Run php artisan pharmanp:module-doctor --openapi before Swagger-heavy changes.',
            'Use --json when a CI job or script needs machine-readable output.',
            'Fix missing folders or provider bindings before writing more feature code.',
        ],
        example: `php artisan pharmanp:module-doctor
php artisan pharmanp:module-doctor --openapi
php artisan pharmanp:module-doctor --json --openapi`,
        practice: [
            'Run the command locally.',
            'Open one warning and find the file it is asking for.',
            'Explain whether the warning is a real architecture issue or a deliberate exception.',
        ],
        mistakes: [
            'Creating folders just to satisfy the doctor when the module does not need them.',
            'Ignoring provider bindings after adding a repository interface.',
            'Adding Swagger comments that do not match real request validation.',
        ],
    },
    {
        key: 'git-sparse',
        title: 'Git workflow and sparse checkout explained plainly',
        audience: 'Frontend / Intern',
        outcome: 'You understand how a React developer can work without pulling the whole Laravel tree.',
        mentalModel: [
            'Sparse checkout limits which files appear in the working directory.',
            'It does not create a second repository. Commits still belong to the same PharmaNP repo.',
            'Use --skip-checks when selecting files like package.json because Git otherwise expects directories.',
        ],
        walkThrough: [
            'Clone with --sparse so the repo starts empty-ish.',
            'Select frontend, resources/js, resources/css and package files.',
            'Run frontend scripts from the repo root.',
            'Commit only frontend files unless your task explicitly includes backend contract changes.',
        ],
        example: `git sparse-checkout set --skip-checks \\
  frontend \\
  resources/js \\
  resources/css \\
  package.json \\
  package-lock.json \\
  vite.config.js`,
        practice: [
            'Explain why package.json caused “not a directory” without --skip-checks.',
            'Run git sparse-checkout list.',
            'Make one frontend-only commit and confirm no backend files changed.',
        ],
        mistakes: [
            'Running sparse-checkout from the wrong directory.',
            'Assuming sparse checkout means frontend and backend are separate histories.',
            'Changing endpoint contracts without coordinating backend.',
        ],
    },
    {
        key: 'large-data-thinking',
        title: 'Large data thinking before large data pain',
        audience: 'Backend / Senior',
        outcome: 'You can tell whether a change will survive serious product, invoice, ledger and report volume.',
        mentalModel: [
            'Server-side pagination is mandatory, but not enough alone.',
            'Large tables need indexed filters, stable sort columns, selected fields and avoided N+1 queries.',
            'Reports need query builders, summaries, date windows and export chunking.',
            'Shared hosting can demo the product. Serious large data needs tuned MySQL, queue workers, cache and monitoring.',
        ],
        walkThrough: [
            'Check every table page query has page and per_page.',
            'Check every searchable/filterable column has a migration index where practical.',
            'Check resources do not lazy-load relationships row by row.',
            'Check exports use chunking or dataset limits.',
            'Check reports default to empty date filters unless business intentionally needs a period.',
        ],
        example: `Bad: Product::all()
Better: Product::query()
  ->select(['id', 'product_code', 'name', 'stock_on_hand', 'updated_at'])
  ->where('name', 'like', $search.'%')
  ->orderByDesc('updated_at')
  ->paginate($perPage)`,
        practice: [
            'Find any all() call in a controller and decide whether it is safe lookup data or a real bug.',
            'Open a report repository and identify date/product/party filters.',
            'Use EXPLAIN on one slow-looking query before adding UI features.',
        ],
        mistakes: [
            'Rendering 10,000 rows in React because the browser seems fine on a laptop.',
            'Sorting by an unindexed text field on every request.',
            'Exporting everything synchronously from a shared-hosting request.',
        ],
    },
    {
        key: 'architecture-review',
        title: 'How senior reviewers should think',
        audience: 'Senior',
        outcome: 'You can review code for maintainability, domain correctness and operational risk, not just syntax.',
        mentalModel: [
            'Good architecture makes wrong changes difficult and common changes boring.',
            'Loose coupling means modules talk through services/contracts, not random shared mutable state.',
            'A clean monolith can be more reliable than fake microservices.',
            'Every abstraction must earn its place by reducing real complexity.',
        ],
        walkThrough: [
            'Start review with the business event: stock, money, tenant scope, reports, permissions.',
            'Then inspect controller thinness, service transaction boundary, repository query quality and resource shape.',
            'Then inspect frontend: table contract, form surface, validation display, mobile layout, keyboard flow.',
            'Finally inspect tests/builds and migration safety.',
        ],
        example: `Review question:
"If this purchase return fails halfway, can stock and supplier payable disagree?"

If the answer is even maybe, the code is not ready.`,
        practice: [
            'Review one payment change and trace its impact into ledger, aging and party balance.',
            'Review one MR change and trace branch/area/division access scope.',
            'Reject one hypothetical shortcut and explain the safer service boundary.',
        ],
        mistakes: [
            'Reviewing only UI polish while accounting logic is duplicated.',
            'Accepting clever generic code that hides domain behavior.',
            'Treating tests as optional because the page works once manually.',
        ],
    },
    {
        key: 'verify-before-push',
        title: 'Verification before push',
        audience: 'Everyone',
        outcome: 'You can prove your change works locally before asking for review.',
        mentalModel: [
            'A green build is not proof of correct business behavior, but a failed build is proof the handoff is not ready.',
            'Use the browser for user-flow verification and tests for regression safety.',
            'Write PR comments like a human: what changed, how verified, what risk remains.',
        ],
        walkThrough: [
            'Run php artisan test for backend changes.',
            'Run npm run build for Laravel-integrated React.',
            'Run npm run frontend:build for standalone frontend shell.',
            'Run php artisan pharmanp:module-doctor --openapi for architecture/API work.',
            'Login locally and use the page you changed.',
        ],
        example: `php artisan test
npm run build
npm run frontend:build
php artisan pharmanp:module-doctor --openapi`,
        practice: [
            'Make one tiny UI change and run both frontend builds.',
            'Make one request validation change and run the relevant feature test.',
            'Open the changed page in browser and confirm the exact user path.',
        ],
        mistakes: [
            'Pushing after only reading code.',
            'Writing robotic PR summaries with escaped newlines.',
            'Leaving local server or generated build errors for the next developer.',
        ],
    },
];

export const modulePlaybooks = [
    {
        title: 'Build Product CRUD the PharmaNP way',
        summary: 'A product touches inventory, division, company/manufacturer, barcode, stock alerts, imports and reports.',
        steps: [
            'Schema: add columns and indexes in the clean schema migration.',
            'Request: validate code, HS code, division, company, unit and prices.',
            'DTO: normalize request fields like product_code, hs_code, group_name and manufacturer_name.',
            'Service: generate missing product code, enforce relationship existence and delegate persistence.',
            'Repository: paginate/search/sort with selected columns and eager relationships.',
            'Resource: expose only fields React needs.',
            'React: table with search/filter/sort/page, expandable summary, modal/drawer form and barcode action.',
            'Swagger/tests: document examples and test create/update/list.',
        ],
    },
    {
        title: 'Build Purchase/Sales transaction screens',
        summary: 'Invoices are not CRUD. They create stock movements, party balances, payment status and printable documents.',
        steps: [
            'Use full-page form for line items and totals.',
            'Support keyboard entry for barcode/product, quantity, rate, discount, tax and payment mode.',
            'Validate every item on backend. Never trust frontend totals.',
            'Wrap invoice, items, batches, stock movement and accounting posting in one transaction.',
            'Return resource with printable URL and payment state.',
            'Reports and aging must read the same source transaction data.',
        ],
    },
    {
        title: 'Build MR/Area/Target feature',
        summary: 'Field force data depends on employee hierarchy, branch, area, division, targets and access scope.',
        steps: [
            'Model employee separately from user login.',
            'Use reports_to_employee_id for hierarchy instead of hardcoding MR -> Manager only.',
            'Attach branch, area and division where reports need scoping.',
            'Targets support monthly, quarterly, annual and primary/secondary types.',
            'Reports apply access scope before aggregating performance.',
            'Frontend hides raw coordinates and shows location name.',
        ],
    },
    {
        title: 'Build Accounting-connected payments',
        summary: 'Payment In/Out must update party due, aging, ledger and cash/bank movement.',
        steps: [
            'Choose direction: in for customer receipt, out for supplier payment.',
            'Optionally allocate to invoices/bills.',
            'Use PaymentSettlementService and accounting posting service.',
            'Update paid amount and due state through source transactions.',
            'Show printable payment and ledger history.',
            'Aging reports must change after payment.',
        ],
    },
];

export const glossary = [
    ['Request', 'Laravel FormRequest that validates and authorizes incoming data.'],
    ['DTO', 'A named data object created from validated input so services do not pass random arrays.'],
    ['Resource', 'Laravel API Resource that shapes output for React and Swagger.'],
    ['Repository Interface', 'Contract a service depends on; provider binds it to the concrete repository.'],
    ['Service', 'Business workflow owner. Transactions, stock/accounting orchestration and domain rules live here.'],
    ['Server table', 'A table where page, per_page, search, sort and filters are handled by backend.'],
    ['JWT', 'Bearer token used by React, Swagger and mobile clients.'],
    ['Module doctor', 'Artisan command checking module folders, bindings and OpenAPI coverage.'],
    ['Access scope', 'Rules for which branch, area, division, company or subordinate data a user can see.'],
    ['Aging', 'Receivable/payable due grouped by days overdue from real invoices/bills/payments.'],
];

export const optionalReinforcement = [
    {
        title: 'React official lessons',
        note: 'Use after reading the React lesson here. Focus on components, state, effects and forms.',
        href: 'https://react.dev/learn',
    },
    {
        title: 'Laravel docs',
        note: 'Use for exact Laravel syntax after you understand PharmaNP module flow.',
        href: 'https://laravel.com/docs',
    },
    {
        title: 'Swagger / OpenAPI',
        note: 'Use when writing annotations or checking API examples.',
        href: 'https://swagger.io/docs/specification/about/',
    },
    {
        title: 'Video reinforcement search',
        note: 'Optional only. Search exact topic after finishing the matching lesson inside this page.',
        href: 'https://www.youtube.com/results?search_query=Laravel+React+JWT+Swagger+modular+architecture+tutorial',
    },
];
