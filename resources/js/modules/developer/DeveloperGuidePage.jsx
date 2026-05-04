import React, { useMemo, useState } from 'react';
import {
    Alert,
    Card,
    Col,
    Collapse,
    Descriptions,
    Divider,
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
    PlayCircleOutlined,
    RocketOutlined,
    SafetyCertificateOutlined,
} from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
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
            title: 'Laravel',
            href: 'https://laravel.com/docs',
            icon: <BookOutlined />,
            detail: 'Requests, resources, services, migrations, queues, validation, and testing.',
        },
        {
            title: 'Video search map',
            href: 'https://www.youtube.com/results?search_query=Laravel+React+Vite+API+architecture+server+side+tables',
            icon: <PlayCircleOutlined />,
            detail: 'Use videos as reinforcement after reading this page, not as the source of truth for this codebase.',
        },
    ], [swaggerUrl]);

    return (
        <div className="page-stack developer-guide-page">
            <PageHeader
                title="Developer Guide"
                subtitle="A practical map for frontend, backend and full-stack contributors working on PharmaNP."
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
                description="Do not open public/frontend-build/index.html directly. Run npm run frontend:dev for development or npm run frontend:build followed by npm run frontend:preview for build testing. If login loops after a backend auth change, clear localStorage key pharmanp.api_token and sign in again."
            />

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
                                    children: 'No secrets in frontend env files. Token mode is for standalone dev/mobile/API testing; same-domain app uses session auth.',
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
