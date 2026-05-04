import React, { useState } from 'react';
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
    Row,
    Space,
    Steps,
    Tabs,
    Tag,
    Timeline,
    Typography,
} from 'antd';
import {
    ApiOutlined,
    BranchesOutlined,
    BugOutlined,
    CheckCircleOutlined,
    CodeOutlined,
    DatabaseOutlined,
    DeploymentUnitOutlined,
    LockOutlined,
    RocketOutlined,
    SafetyCertificateOutlined,
    VideoCameraOutlined,
} from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { apiBaseUrl, apiUrl } from '../../core/utils/url';
import {
    glossary,
    learningPaths as curriculumPaths,
    lessons as curriculumLessons,
    modulePlaybooks,
    optionalReinforcement,
} from './developerGuideCurriculum';

const { Paragraph, Text, Title } = Typography;

const shellCommands = {
    frontend: [
        'git clone --filter=blob:none --sparse https://github.com/prateekbhujel/pharmanp.git pharmanp-frontend',
        'cd pharmanp-frontend',
        'git sparse-checkout set --skip-checks frontend resources/js resources/css package.json package-lock.json vite.config.js',
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

function LessonCard({ lesson }) {
    return (
        <Card className="developer-lesson-card" title={lesson.title} extra={<Tag color="geekblue">{lesson.audience}</Tag>}>
            <div className="developer-stack developer-stack-lg">
                <Alert type="success" showIcon message="What you should be able to do" description={lesson.outcome} />
                <Collapse
                    bordered={false}
                    defaultActiveKey={['model']}
                    items={[
                        {
                            key: 'model',
                            label: 'Mental model',
                            children: <Checklist items={lesson.mentalModel} />,
                        },
                        {
                            key: 'walkthrough',
                            label: 'Teach me step by step',
                            children: <Checklist items={lesson.walkThrough} />,
                        },
                        {
                            key: 'example',
                            label: 'Concrete example',
                            children: <pre className="developer-command-block">{lesson.example}</pre>,
                        },
                        {
                            key: 'practice',
                            label: 'Practice inside this codebase',
                            children: <Checklist items={lesson.practice} />,
                        },
                        {
                            key: 'mistakes',
                            label: 'Mistakes to avoid',
                            children: <Checklist items={lesson.mistakes} />,
                        },
                    ]}
                />
            </div>
        </Card>
    );
}

function LearningPathCard({ path }) {
    const pathLessons = path.lessons
        .map((key) => curriculumLessons.find((lesson) => lesson.key === key))
        .filter(Boolean);

    return (
        <Card className="developer-path-card" size="small">
            <div className="developer-stack">
                <Space align="center" wrap>
                    <Tag color="blue">{path.minutes} min</Tag>
                    <Text strong>{path.title}</Text>
                </Space>
                <Paragraph className="!m-0 text-slate-600">{path.promise}</Paragraph>
                <div className="developer-path-steps">
                    {pathLessons.map((lesson, index) => (
                        <button
                            type="button"
                            key={lesson.key}
                            onClick={() => document.getElementById(`lesson-${lesson.key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                        >
                            <span>{index + 1}</span>
                            <strong>{lesson.title}</strong>
                        </button>
                    ))}
                </div>
            </div>
        </Card>
    );
}

function ModulePlaybookCard({ playbook }) {
    return (
        <Card className="developer-playbook-card" title={playbook.title}>
            <div className="developer-stack">
                <Paragraph className="!m-0 text-slate-600">{playbook.summary}</Paragraph>
                <Checklist items={playbook.steps} />
            </div>
        </Card>
    );
}

function TeacherPanel() {
    return (
        <div className="developer-stack developer-stack-lg">
            <Card title="Start Here: Choose the brain you are bringing today" className="developer-guide-card">
                <Row gutter={[14, 14]}>
                    {curriculumPaths.map((path) => (
                        <Col xs={24} md={12} xl={8} key={path.key}>
                            <LearningPathCard path={path} />
                        </Col>
                    ))}
                </Row>
            </Card>

            <Card title="How PharmaNP wants you to think" className="developer-guide-card">
                <Row gutter={[14, 14]}>
                    <Col xs={24} lg={8}>
                        <div className="developer-principle-card">
                            <DeploymentUnitOutlined />
                            <Text strong>Module first</Text>
                            <Paragraph className="!m-0">
                                Start from the business module. Inventory, Purchase, Sales, Accounting, Party, MR, Reports and Setup should not know each other through random shortcuts.
                            </Paragraph>
                        </div>
                    </Col>
                    <Col xs={24} lg={8}>
                        <div className="developer-principle-card">
                            <DatabaseOutlined />
                            <Text strong>Transaction safe</Text>
                            <Paragraph className="!m-0">
                                Stock, accounting, payments, returns and import confirmation are service transactions. A half-saved invoice is worse than no invoice.
                            </Paragraph>
                        </div>
                    </Col>
                    <Col xs={24} lg={8}>
                        <div className="developer-principle-card">
                            <CodeOutlined />
                            <Text strong>Frontend respects API</Text>
                            <Paragraph className="!m-0">
                                React pages consume endpoint keys and resources. They do not guess hidden Eloquent fields or invent second validation systems.
                            </Paragraph>
                        </div>
                    </Col>
                </Row>
            </Card>

            <Card title="Lesson Library" className="developer-guide-card">
                <div className="developer-lesson-grid">
                    {curriculumLessons.map((lesson) => (
                        <div id={`lesson-${lesson.key}`} key={lesson.key}>
                            <LessonCard lesson={lesson} />
                        </div>
                    ))}
                </div>
            </Card>

            <Card title="Real module playbooks" className="developer-guide-card">
                <Row gutter={[14, 14]}>
                    {modulePlaybooks.map((playbook) => (
                        <Col xs={24} lg={12} key={playbook.title}>
                            <ModulePlaybookCard playbook={playbook} />
                        </Col>
                    ))}
                </Row>
            </Card>

            <Row gutter={[16, 16]}>
                <Col xs={24} lg={14}>
                    <Card title="Vocabulary developers must share" className="developer-guide-card">
                        <div className="developer-glossary-grid">
                            {glossary.map(([term, definition]) => (
                                <div className="developer-glossary-item" key={term}>
                                    <Text strong>{term}</Text>
                                    <Text type="secondary">{definition}</Text>
                                </div>
                            ))}
                        </div>
                    </Card>
                </Col>
                <Col xs={24} lg={10}>
                    <Card title="Optional reinforcement, not the lesson" className="developer-guide-card">
                        <Paragraph className="text-slate-600">
                            These links are only backup material. The PharmaNP-specific workflow is already explained above, so nobody should need to leave this page to understand what to build.
                        </Paragraph>
                        <div className="developer-resource-list">
                            {optionalReinforcement.map((item) => (
                                <a href={item.href} key={item.href} target="_blank" rel="noreferrer" className="developer-inline-resource">
                                    <VideoCameraOutlined />
                                    <span>
                                        <strong>{item.title}</strong>
                                        <small>{item.note}</small>
                                    </span>
                                </a>
                            ))}
                        </div>
                    </Card>
                </Col>
            </Row>
        </div>
    );
}

function TutorialPanel({ mode }) {
    if (mode === 'teacher') {
        return <TeacherPanel />;
    }

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

function PracticePanel() {
    return (
        <div className="developer-stack developer-stack-lg">
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
        </div>
    );
}

function ReferencePanel() {
    return (
        <div className="developer-stack developer-stack-lg">
            <Row gutter={[16, 16]}>
                {Object.values(rolePaths).map((item) => (
                    <Col xs={24} md={12} xl={8} key={item.title}>
                        <FocusCard item={item} />
                    </Col>
                ))}
            </Row>

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

            <Card className="developer-guide-card">
                <Paragraph className="!mb-0 text-slate-600">
                    The fastest way to contribute is to change one module at a time, keep the API envelope stable, verify with tests/builds, and leave the next developer with fewer surprises than you found.
                </Paragraph>
            </Card>
        </div>
    );
}

function GuideTabLabel({ icon, label, note }) {
    return (
        <span className="developer-guide-tab-label">
            {icon}
            <span>
                <strong>{label}</strong>
                <small>{note}</small>
            </span>
        </span>
    );
}

export function DeveloperGuidePage() {
    const [unlocked, setUnlocked] = useState(() => localStorage.getItem(developerGuideStorageKey) === 'true');
    const displayedApiBaseUrl = apiBaseUrl || window.location.origin;
    const swaggerUrl = apiUrl('/api/documentation');

    if (! unlocked) {
        return <DeveloperGuideGate onUnlock={() => setUnlocked(true)} />;
    }

    return (
        <div className="page-stack developer-guide-page">
            <PageHeader
                title="Developer Guide"
                description="An in-app teacher for frontend, backend and full-stack contributors working on PharmaNP."
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
                            <Title level={2} className="!m-0">A teacher living inside the codebase.</Title>
                            <Paragraph className="!m-0 text-slate-600">
                                This page teaches the way PharmaNP is built: React pages, Laravel modules, JWT, Swagger, repository contracts, DTOs, services, server tables, transaction safety and review habits. It is written so an intern can start and a senior can still use it as the project contract.
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

            <Card className="developer-guide-tabs-card">
                <Tabs
                    size="large"
                    destroyInactiveTabPane={false}
                    items={[
                        {
                            key: 'start',
                            label: <GuideTabLabel icon={<RocketOutlined />} label="Start Here" note="Learning paths" />,
                            children: <TeacherPanel />,
                        },
                        {
                            key: 'frontend',
                            label: <GuideTabLabel icon={<CodeOutlined />} label="Frontend" note="React shell" />,
                            children: <TutorialPanel mode="frontend" />,
                        },
                        {
                            key: 'backend',
                            label: <GuideTabLabel icon={<DatabaseOutlined />} label="Backend" note="Laravel modules" />,
                            children: <TutorialPanel mode="backend" />,
                        },
                        {
                            key: 'fullstack',
                            label: <GuideTabLabel icon={<BranchesOutlined />} label="Full-stack" note="End-to-end slice" />,
                            children: <TutorialPanel mode="fullstack" />,
                        },
                        {
                            key: 'labs',
                            label: <GuideTabLabel icon={<BugOutlined />} label="Labs" note="Practice and verify" />,
                            children: <PracticePanel />,
                        },
                        {
                            key: 'reference',
                            label: <GuideTabLabel icon={<SafetyCertificateOutlined />} label="Reference" note="Rules and review" />,
                            children: <ReferencePanel />,
                        },
                    ]}
                />
            </Card>
        </div>
    );
}
