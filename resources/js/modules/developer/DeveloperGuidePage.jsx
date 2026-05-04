import React, { useMemo, useState } from 'react';
import {
    Alert,
    App,
    Button,
    Card,
    Col,
    Collapse,
    Descriptions,
    Divider,
    Form,
    Input,
    Progress,
    Result,
    Row,
    Segmented,
    Space,
    Steps,
    Tag,
    Timeline,
    Typography,
} from 'antd';
import {
    ApiOutlined,
    BookOutlined,
    BranchesOutlined,
    BugOutlined,
    CheckCircleOutlined,
    CodeOutlined,
    DatabaseOutlined,
    DeploymentUnitOutlined,
    ExperimentOutlined,
    FileSearchOutlined,
    ForkOutlined,
    LaptopOutlined,
    LockOutlined,
    PlayCircleOutlined,
    RocketOutlined,
    SafetyCertificateOutlined,
    ThunderboltOutlined,
    ToolOutlined,
    VideoCameraOutlined,
} from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { apiBaseUrl, apiUrl } from '../../core/utils/url';

const { Paragraph, Text, Title } = Typography;

const shellCommands = {
    frontend: [
        'git clone --filter=blob:none --sparse https://github.com/prateekbhujel/pharmanp.git pharmanp-frontend',
        'cd pharmanp-frontend',
        'git sparse-checkout set --skip-checks frontend resources/js resources/css package.json package-lock.json',
        'npm install',
        'cp frontend/.env.example frontend/.env',
        'npm run frontend:dev',
    ],
    frontendPreview: [
        'npm run frontend:build',
        'npm run frontend:preview',
    ],
    backend: [
        'composer install',
        'npm install',
        'cp .env.example .env',
        'php artisan key:generate',
        'php artisan migrate',
        'php artisan test',
        'npm run build',
        'php artisan serve',
    ],
};

const rolePaths = {
    intern: {
        title: 'Intern / First Week',
        goal: 'Run the app, understand folders, make safe UI changes, and verify before pushing.',
        focus: ['Read the page flow first', 'Use existing components', 'Do not invent API shapes', 'Ask with file path and route'],
    },
    frontend: {
        title: 'Frontend Developer',
        goal: 'Work from the React shell with Swagger/API responses, no local PHP required.',
        focus: ['Module route files', 'ServerTable pattern', 'Form modal/full-page decisions', 'Token auth in frontend/.env'],
    },
    backend: {
        title: 'Backend Developer',
        goal: 'Build module APIs through requests, DTOs, services, repository interfaces, resources, and tests.',
        focus: ['Thin controllers', 'Transactions in services', 'Repository contracts', 'OpenAPI annotations'],
    },
    fullstack: {
        title: 'Full-stack Developer',
        goal: 'Own a workflow from database migration to Swagger to React list/form/report.',
        focus: ['API envelope', 'Permission strings', 'Pagination contract', 'Build and test loop'],
    },
    senior: {
        title: 'Senior Reviewer',
        goal: 'Review boundaries, data integrity, tenant scope, performance, and operational risk.',
        focus: ['No foreign keys by policy', 'Indexes for filters', 'No all-row tables', 'Accounting/stock transactions'],
    },
};

const backendModuleShape = [
    'DTOs: convert validated arrays into predictable business payloads.',
    'Http/Requests: authorize and validate every write or filtered read.',
    'Http/Resources: own the JSON response shape.',
    'Repositories/Interfaces: contracts used by services.',
    'Repositories: reusable query details and persistence operations.',
    'Services: transactions, accounting posting, stock posting, and domain orchestration.',
    'Providers: bind repository interfaces and load module routes.',
    'Routes: API route boundary owned by the module.',
];

const frontendModuleShape = [
    'routes.jsx: lazy module registration and app route mapping.',
    'Page component: list, filter, modal/drawer/full-page workflow.',
    'core/api/endpoints.js: one place for API URLs.',
    'core/hooks/useServerTable.js: shared server-side pagination/sort/filter behavior.',
    'core/components: reusable table, badge, date, confirmation, drawer and money components.',
    'No direct hardcoded API shape assumptions outside the page/service boundary.',
];

const apiEnvelope = `{
  "status": "success",
  "code": 200,
  "message": "Products retrieved successfully.",
  "data": [],
  "links": {},
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 0
  }
}`;

const frontendEnv = `VITE_PHARMANP_API_BASE_URL=https://pharmanp.pratikbhujel.com.np
VITE_PHARMANP_APP_BASE_URL=https://pharmanp.pratikbhujel.com.np
VITE_PHARMANP_MEDIA_BASE_URL=https://pharmanp.pratikbhujel.com.np/storage
VITE_PHARMANP_STANDALONE=true
VITE_PHARMANP_AUTH_MODE=token
VITE_PHARMANP_API_TOKEN=
VITE_PHARMANP_ENV=development
VITE_PHARMANP_USE_PROXY=false`;

const developerGuideStorageKey = 'pharmanp.developer_guide.unlocked';

const learningTracks = [
    {
        title: 'React Foundations',
        level: 'Intern / Frontend',
        progress: 35,
        icon: <CodeOutlined />,
        outcome: 'Understand components, state, effects, controlled forms and how PharmaNP pages are assembled.',
        steps: [
            'Read React component basics and rebuild one small read-only card from scratch.',
            'Trace ProductsPage from route registration to ServerTable response rendering.',
            'Change one filter label, build, and verify that no API contract changed.',
        ],
        resources: [
            ['React official Learn', 'https://react.dev/learn'],
            ['React state and forms video search', 'https://www.youtube.com/results?search_query=React+controlled+forms+state+effects+tutorial'],
        ],
    },
    {
        title: 'Laravel API Foundations',
        level: 'Intern / Backend',
        progress: 42,
        icon: <DatabaseOutlined />,
        outcome: 'Understand requests, validation, resources, services, repositories, migrations and tests.',
        steps: [
            'Open one module route file and follow Controller -> Request -> Service -> Repository.',
            'Add one validation rule in a FormRequest and verify the JSON error envelope.',
            'Run the related feature test before touching frontend code.',
        ],
        resources: [
            ['Laravel documentation', 'https://laravel.com/docs'],
            ['Laravel API resources video search', 'https://www.youtube.com/results?search_query=Laravel+API+resources+FormRequest+service+repository+tutorial'],
        ],
    },
    {
        title: 'PharmaNP Modular Workflow',
        level: 'Full-stack',
        progress: 58,
        icon: <DeploymentUnitOutlined />,
        outcome: 'Ship one workflow without breaking auth, accounting, stock movement or frontend pagination.',
        steps: [
            'Start from database/indexing needs, then define API request/response examples.',
            'Keep write logic in services and reusable query logic in repositories.',
            'Wire React only after Swagger and tests prove the API contract.',
        ],
        resources: [
            ['Swagger/OpenAPI docs', 'https://swagger.io/docs/specification/about/'],
            ['OpenAPI Laravel tutorial search', 'https://www.youtube.com/results?search_query=Laravel+OpenAPI+Swagger+JWT+API+documentation'],
        ],
    },
    {
        title: 'ERP Domain Thinking',
        level: 'Senior / Reviewer',
        progress: 64,
        icon: <SafetyCertificateOutlined />,
        outcome: 'Reason about stock, accounting, due aging, targets, returns and reports before writing UI.',
        steps: [
            'Never post stock/accounting writes outside a transaction service.',
            'Never make a report from fake manual summary tables when source transactions exist.',
            'Always verify tenant/company/branch scope and indexed filters.',
        ],
        resources: [
            ['Inventory accounting search', 'https://www.youtube.com/results?search_query=inventory+accounting+purchases+sales+returns+ledger+ERP'],
            ['Database indexing search', 'https://www.youtube.com/results?search_query=MySQL+indexing+pagination+large+tables+Laravel'],
        ],
    },
];

const guidedLabs = [
    {
        title: 'Lab 1: Read a Server Table',
        role: 'Frontend',
        files: ['resources/js/core/hooks/useServerTable.js', 'resources/js/modules/inventory/ProductsPage.jsx'],
        task: 'Trace search, sort, page and per_page from Ant Design Table into the API query string.',
        verify: 'Change page size to 15, search a product, refresh, and confirm data still comes from backend pagination.',
    },
    {
        title: 'Lab 2: Build a Safe Backend Field',
        role: 'Backend',
        files: ['database/migrations/0001_01_01_000000_create_pharmanp_schema.php', 'app/Modules/Inventory/Http/Requests/ProductRequest.php', 'app/Modules/Inventory/Http/Resources/ProductResource.php'],
        task: 'Add a nullable indexed lookup-style field through migration, request, service/repository and resource.',
        verify: 'Run product API tests and inspect Swagger response shape.',
    },
    {
        title: 'Lab 3: Wire Swagger to React',
        role: 'Full-stack',
        files: ['app/Modules/*/Http/Controllers/*Controller.php', 'resources/js/core/api/endpoints.js', 'resources/js/modules/*'],
        task: 'Use Swagger Authorize with the copied JWT token, test the API manually, then wire the page to the endpoint.',
        verify: 'Run npm run build and reproduce the request from browser devtools.',
    },
    {
        title: 'Lab 4: Transaction Safety Review',
        role: 'Senior',
        files: ['app/Modules/Purchase/Services', 'app/Modules/Sales/Services', 'app/Modules/Accounting/Services'],
        task: 'Find the transaction boundary and confirm stock/accounting posting lives in services, not controllers.',
        verify: 'Run transaction posting tests and confirm rollback behavior is covered.',
    },
];

const architectureFlow = [
    { title: 'Route', description: 'Module-owned Routes/api.php grouped under /api/v1 with JWT middleware.' },
    { title: 'Request', description: 'FormRequest validates and authorizes. No controller inline validation for writes.' },
    { title: 'DTO', description: 'Validated payload becomes predictable application data where the workflow needs it.' },
    { title: 'Service', description: 'Business transaction, stock/accounting coordination, tenant-aware rules.' },
    { title: 'Repository Contract', description: 'Service depends on an interface so queries stay swappable and testable.' },
    { title: 'Resource', description: 'Frontend receives a stable envelope and response shape.' },
    { title: 'React Module', description: 'Endpoint key, page, table filters, modal/drawer/full-page form and validation rendering.' },
];

function CommandBlock({ title, commands }) {
    return (
        <Card size="small" className="developer-guide-card">
            <div className="developer-stack">
                <Text strong>{title}</Text>
                <pre className="developer-command-block">{commands.join('\n')}</pre>
            </div>
        </Card>
    );
}

function FocusCard({ item }) {
    return (
        <Card className="developer-guide-card" size="small">
            <div className="developer-stack">
                <Title level={5} className="!m-0">{item.title}</Title>
                <Paragraph className="!m-0 text-slate-600">{item.goal}</Paragraph>
                <Space size={[6, 6]} wrap>
                    {item.focus.map((focus) => <Tag color="green" key={focus}>{focus}</Tag>)}
                </Space>
            </div>
        </Card>
    );
}

function Checklist({ items }) {
    return (
        <div className="developer-checklist">
            {items.map((item) => (
                <div className="developer-checklist-item" key={item}>
                    <CheckCircleOutlined className="developer-checklist-icon" />
                    <span>{item}</span>
                </div>
            ))}
        </div>
    );
}

function DeveloperGuideGate({ onUnlock }) {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [loading, setLoading] = useState(false);

    async function submit(values) {
        setLoading(true);
        try {
            await http.post(endpoints.developerGuideAccess, values);
            localStorage.setItem(developerGuideStorageKey, 'true');
            notification.success({ message: 'Developer guide unlocked' });
            onUnlock();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({
                message: 'Access denied',
                description: error?.response?.data?.message || 'Check the developer access code and try again.',
            });
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="developer-guide-access">
            <Card className="developer-guide-access-card">
                <div className="developer-stack developer-stack-lg">
                    <Space size={10} align="center">
                        <span className="developer-resource-icon"><LockOutlined /></span>
                        <Tag color="blue">Internal training center</Tag>
                    </Space>
                    <Title level={2} className="!m-0">Unlock PharmaNP Developer Guide</Title>
                    <Paragraph className="!m-0 text-slate-600">
                        This guide is for developers joining the project. It teaches the workflow, module boundaries, API contract, frontend shell, backend services, testing loop and review standards.
                    </Paragraph>
                    <Form form={form} layout="vertical" onFinish={submit}>
                        <Form.Item name="code" label="Developer access code" rules={[{ required: true, message: 'Enter the developer access code.' }]}>
                            <Input.Password
                                inputMode="numeric"
                                maxLength={10}
                                prefix={<LockOutlined />}
                                placeholder="Enter access code"
                                size="large"
                            />
                        </Form.Item>
                        <Button type="primary" htmlType="submit" loading={loading} size="large" block>
                            Open Developer Guide
                        </Button>
                    </Form>
                    <Alert
                        type="info"
                        showIcon
                        message="Access code is validated by the backend"
                        description="The code is not editable from settings or any admin screen. Keep this page for people who are actually working on the codebase."
                    />
                </div>
            </Card>
        </div>
    );
}

function TutorialPanel({ mode }) {
    if (mode === 'frontend') {
        return (
            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <CommandBlock title="Frontend-only dev shell" commands={shellCommands.frontend} />
                </Col>
                <Col xs={24} lg={12}>
                    <CommandBlock title="Production build check" commands={shellCommands.frontendPreview} />
                </Col>
                <Col xs={24}>
                    <Card title="How frontend work should flow" className="developer-guide-card">
                        <Steps
                            direction="vertical"
                            items={[
                                { title: 'Pick the module route', description: 'Open resources/js/modules/<module>/routes.jsx and find the page component.' },
                                { title: 'Use the shared API endpoint', description: 'Add or reuse a key in resources/js/core/api/endpoints.js.' },
                                { title: 'Load lists through ServerTable', description: 'Tables must stay server-side paginated and sortable.' },
                                { title: 'Submit through the module page', description: 'Use modal/drawer/full page based on form size. Show Laravel validation errors cleanly.' },
                                { title: 'Verify in frontend shell and Laravel build', description: 'Run npm run frontend:dev while developing, then npm run frontend:build before handoff.' },
                            ]}
                        />
                    </Card>
                </Col>
                <Col xs={24}>
                    <Card title="Frontend .env contract" className="developer-guide-card">
                        <Paragraph className="text-slate-600">
                            PharmaNP uses Vite env names, not CRA-style REACT_APP names. The frontend shell talks to the backend through one JWT API base URL.
                        </Paragraph>
                        <pre className="developer-command-block">{frontendEnv}</pre>
                        <Paragraph className="!mb-0 text-slate-600">
                            To debug a client issue in Swagger, sign in from the frontend shell and run <Text code>localStorage.getItem('pharmanp.api_token')</Text>. Paste it into Swagger as <Text code>Bearer token</Text>.
                        </Paragraph>
                    </Card>
                </Col>
            </Row>
        );
    }

    if (mode === 'backend') {
        return (
            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <CommandBlock title="Backend local loop" commands={shellCommands.backend} />
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Module anatomy" className="developer-guide-card">
                        <Checklist items={backendModuleShape} />
                    </Card>
                </Col>
                <Col xs={24}>
                    <Card title="Controller to database flow" className="developer-guide-card">
                        <Timeline
                            items={[
                                { dot: <ApiOutlined />, children: 'Controller receives FormRequest, authorizes, creates DTO, calls service, returns Resource/JSON.' },
                                { dot: <DeploymentUnitOutlined />, children: 'Service owns business transaction and calls repository contracts plus accounting/stock services.' },
                                { dot: <DatabaseOutlined />, children: 'Repository hides reusable query and persistence details. No raw SQL concatenation.' },
                                { dot: <SafetyCertificateOutlined />, children: 'Tests cover stock, accounting, auth, import preview, and module architecture contracts.' },
                            ]}
                        />
                    </Card>
                </Col>
            </Row>
        );
    }

    if (mode === 'fullstack') {
        return (
            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <Card title="One feature, correct order" className="developer-guide-card">
                        <Steps
                            direction="vertical"
                            items={[
                                { title: 'Database', description: 'Add columns/indexes in the clean schema. Relationship IDs are indexed, no DB-level FK constraints.' },
                                { title: 'Backend module', description: 'Request -> DTO -> Service -> Repository Interface -> Repository -> Resource.' },
                                { title: 'Swagger/API docs', description: 'Expose request/response examples so frontend can test without reading Laravel internals.' },
                                { title: 'Frontend module', description: 'Route, endpoint key, page, table filters, form, validation handling.' },
                                { title: 'Verification', description: 'php artisan test, npm run build, npm run frontend:build, module doctor.' },
                            ]}
                        />
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="API envelope frontend expects" className="developer-guide-card">
                        <pre className="developer-command-block">{apiEnvelope}</pre>
                    </Card>
                </Col>
            </Row>
        );
    }

    return (
        <Row gutter={[16, 16]}>
            {Object.values(rolePaths).map((item) => (
                <Col xs={24} md={12} xl={8} key={item.title}>
                    <FocusCard item={item} />
                </Col>
            ))}
        </Row>
    );
}

export function DeveloperGuidePage() {
    const [mode, setMode] = useState('overview');
    const [unlocked, setUnlocked] = useState(() => localStorage.getItem(developerGuideStorageKey) === 'true');
    const displayedApiBaseUrl = apiBaseUrl || window.location.origin;
    const swaggerUrl = apiUrl('/api/documentation');
    const resources = useMemo(() => [
        {
            title: 'Swagger / API Docs',
            href: swaggerUrl,
            icon: <ApiOutlined />,
            detail: 'Use Authorize with a bearer token. Frontend developers should test contracts here before wiring pages.',
        },
        {
            title: 'React + Vite',
            href: 'https://react.dev/learn',
            icon: <CodeOutlined />,
            detail: 'Component thinking, state, effects, and rendering behavior.',
        },
        {
            title: 'Vite Guide',
            href: 'https://vite.dev/guide/',
            icon: <LaptopOutlined />,
            detail: 'Frontend dev server, environment variables, builds and preview behavior.',
        },
        {
            title: 'Laravel',
            href: 'https://laravel.com/docs',
            icon: <BookOutlined />,
            detail: 'Requests, resources, services, migrations, queues, validation, and testing.',
        },
        {
            title: 'PharmaNP API Flow',
            href: 'https://www.youtube.com/results?search_query=JWT+Bearer+Swagger+React+Laravel+API+workflow',
            icon: <ThunderboltOutlined />,
            detail: 'JWT, Swagger Authorize, browser token copying and API debugging flow.',
        },
        {
            title: 'Database Practice',
            href: 'https://www.youtube.com/results?search_query=MySQL+indexes+server+side+pagination+Laravel+large+tables',
            icon: <FileSearchOutlined />,
            detail: 'Indexes, filtered queries, server pagination and slow-query thinking.',
        },
        {
            title: 'Testing Workflow',
            href: 'https://laravel.com/docs/testing',
            icon: <ExperimentOutlined />,
            detail: 'Feature tests, transaction tests and regression protection.',
        },
        {
            title: 'Git Workflow',
            href: 'https://www.youtube.com/results?search_query=Git+branch+pull+request+code+review+workflow+for+developers',
            icon: <ForkOutlined />,
            detail: 'Branch discipline, clean commits and review handoff.',
        },
        {
            title: 'Ant Design',
            href: 'https://ant.design/components/overview/',
            icon: <ToolOutlined />,
            detail: 'Tables, forms, drawers, modals and professional admin UI components.',
        },
        {
            title: 'Video search map',
            href: 'https://www.youtube.com/results?search_query=Laravel+React+Vite+API+architecture+server+side+tables',
            icon: <PlayCircleOutlined />,
            detail: 'Use videos as reinforcement after reading this page, not as the source of truth for this codebase.',
        },
    ], [swaggerUrl]);

    if (! unlocked) {
        return <DeveloperGuideGate onUnlock={() => setUnlocked(true)} />;
    }

    return (
        <div className="page-stack developer-guide-page">
            <PageHeader
                title="Developer Guide"
                subtitle="A practical map for frontend, backend and full-stack contributors working on PharmaNP."
                actions={(
                    <Button
                        icon={<LockOutlined />}
                        onClick={() => {
                            localStorage.removeItem(developerGuideStorageKey);
                            setUnlocked(false);
                        }}
                    >
                        Lock Guide
                    </Button>
                )}
            />

            <Card className="developer-guide-hero">
                <Row gutter={[20, 20]} align="middle">
                    <Col xs={24} lg={15}>
                        <div className="developer-stack developer-stack-lg">
                            <Space size={8} wrap>
                                <Tag color="blue">Local login: pratik@admin.com</Tag>
                                <Tag color="green">Password: password</Tag>
                                <Tag color="purple">API envelope first</Tag>
                            </Space>
                            <Title level={2} className="!m-0">Work like the app is already yours.</Title>
                            <Paragraph className="!m-0 text-slate-600">
                                This page is the contract between backend, frontend and full-stack work. Follow the flow here before changing module code, API shape, table behavior, auth, accounting or stock logic.
                            </Paragraph>
                        </div>
                    </Col>
                    <Col xs={24} lg={9}>
                        <Descriptions bordered size="small" column={1}>
                            <Descriptions.Item label="Frontend API">{displayedApiBaseUrl}</Descriptions.Item>
                            <Descriptions.Item label="API docs">{swaggerUrl}</Descriptions.Item>
                            <Descriptions.Item label="Frontend shell">npm run frontend:dev</Descriptions.Item>
                            <Descriptions.Item label="Preview build">npm run frontend:preview</Descriptions.Item>
                        </Descriptions>
                    </Col>
                </Row>
            </Card>

            <Alert
                type="info"
                showIcon
                icon={<BugOutlined />}
                title="If the frontend shell appears to reload on click"
                description="Do not open public/frontend-build/index.html directly. Run npm run frontend:dev for development or npm run frontend:build followed by npm run frontend:preview for build testing. If login loops after an auth change, clear localStorage key pharmanp.api_token and sign in again."
            />

            <Card title="Choose Your Learning Track" className="developer-guide-card">
                <Row gutter={[14, 14]}>
                    {learningTracks.map((track) => (
                        <Col xs={24} md={12} xl={6} key={track.title}>
                            <div className="developer-track-card">
                                <Space size={10} align="start">
                                    <span className="developer-resource-icon">{track.icon}</span>
                                    <div className="developer-stack">
                                        <div>
                                            <Text strong>{track.title}</Text>
                                            <div><Text type="secondary">{track.level}</Text></div>
                                        </div>
                                        <Progress percent={track.progress} showInfo={false} strokeColor="#0f766e" trailColor="#e2e8f0" />
                                        <Paragraph className="!m-0 text-slate-600">{track.outcome}</Paragraph>
                                    </div>
                                </Space>
                            </div>
                        </Col>
                    ))}
                </Row>
            </Card>

            <Segmented
                block
                value={mode}
                onChange={setMode}
                options={[
                    { label: 'Overview', value: 'overview', icon: <RocketOutlined /> },
                    { label: 'Frontend', value: 'frontend', icon: <CodeOutlined /> },
                    { label: 'Backend', value: 'backend', icon: <DatabaseOutlined /> },
                    { label: 'Full-stack', value: 'fullstack', icon: <BranchesOutlined /> },
                ]}
            />

            <TutorialPanel mode={mode} />

            <Card title="Architecture Map" className="developer-guide-card">
                <Steps
                    current={-1}
                    responsive
                    items={architectureFlow.map((item) => ({
                        title: item.title,
                        description: item.description,
                    }))}
                />
            </Card>

            <Card title="Guided Practice Labs" className="developer-guide-card">
                <Row gutter={[14, 14]}>
                    {guidedLabs.map((lab) => (
                        <Col xs={24} lg={12} key={lab.title}>
                            <Card size="small" className="developer-lab-card" title={lab.title} extra={<Tag color="cyan">{lab.role}</Tag>}>
                                <div className="developer-stack">
                                    <Paragraph className="!m-0">{lab.task}</Paragraph>
                                    <div>
                                        <Text strong>Files to inspect</Text>
                                        <div className="developer-file-list">
                                            {lab.files.map((file) => <Text code key={file}>{file}</Text>)}
                                        </div>
                                    </div>
                                    <Alert type="success" showIcon message="Verification" description={lab.verify} />
                                </div>
                            </Card>
                        </Col>
                    ))}
                </Row>
            </Card>

            <Card title="Tutorial Roadmap" className="developer-guide-card">
                <Collapse
                    bordered={false}
                    items={learningTracks.map((track) => ({
                        key: track.title,
                        label: (
                            <Space>
                                {track.icon}
                                <span>{track.title}</span>
                                <Tag>{track.level}</Tag>
                            </Space>
                        ),
                        children: (
                            <Row gutter={[12, 12]}>
                                <Col xs={24} lg={13}>
                                    <Checklist items={track.steps} />
                                </Col>
                                <Col xs={24} lg={11}>
                                    <div className="developer-resource-list">
                                        {track.resources.map(([label, href]) => (
                                            <a href={href} key={href} target="_blank" rel="noreferrer" className="developer-inline-resource">
                                                <VideoCameraOutlined />
                                                <span>{label}</span>
                                            </a>
                                        ))}
                                    </div>
                                </Col>
                            </Row>
                        ),
                    }))}
                />
            </Card>

            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <Card title="Frontend module rules" className="developer-guide-card">
                        <Checklist items={frontendModuleShape} />
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Code review checklist" className="developer-guide-card">
                        <Collapse
                            bordered={false}
                            items={[
                                {
                                    key: 'data',
                                    label: 'Data and performance',
                                    children: 'No list should load all rows. Use server-side pagination, searchable indexed columns, and clear per_page behavior. Do not hide N+1 queries under resources.',
                                },
                                {
                                    key: 'stock',
                                    label: 'Stock and accounting',
                                    children: 'Purchases, sales, returns, payments, vouchers and stock adjustments must go through service transactions and shared posting services.',
                                },
                                {
                                    key: 'frontend',
                                    label: 'Frontend consistency',
                                    children: 'Use shared table, badge, date, money, confirmation and drawer/modal components. Keep forms aligned with legacy behavior but not legacy Blade structure.',
                                },
                                {
                                    key: 'security',
                                    label: 'Security and auth',
                                    children: 'No secrets in frontend env files. React, Swagger and mobile all use the same JWT bearer API auth.',
                                },
                            ]}
                        />
                    </Card>
                </Col>
            </Row>

            <Card title="Reference links" className="developer-guide-card">
                <Row gutter={[12, 12]}>
                    {resources.map((item) => (
                        <Col xs={24} md={12} xl={6} key={item.title}>
                            <a className="developer-resource-link" href={item.href} target={item.href.startsWith('http') ? '_blank' : undefined} rel="noreferrer">
                                <div className="developer-stack">
                                    <span className="developer-resource-icon">{item.icon}</span>
                                    <Text strong>{item.title}</Text>
                                    <Text type="secondary">{item.detail}</Text>
                                </div>
                            </a>
                        </Col>
                    ))}
                </Row>
            </Card>

            <Divider />
            <Paragraph className="text-slate-500">
                The fastest way to contribute is to change one module at a time, keep the API envelope stable, verify with tests/builds, and leave the next developer with fewer surprises than you found.
            </Paragraph>
        </div>
    );
}
