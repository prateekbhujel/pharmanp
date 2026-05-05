import React, { useEffect, useState } from 'react';
import { Space } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';
import { MrDashboardPanel } from './MrDashboardPanel';
import { MrRepresentativesPanel } from './MrRepresentativesPanel';
import { MrVisitsPanel } from './MrVisitsPanel';
import { MrBranchesPanel } from './MrBranchesPanel';

const fieldForceSections = {
    dashboard: { title: 'Dashboard' },
    performance: { title: 'Performance' },
    representatives: { title: 'Representatives' },
    visits: { title: 'Visits' },
    branches: { title: 'Branches' },
};

function currentSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();
    return fieldForceSections[section] ? section : 'dashboard';
}

export function MrTrackingPage() {
    const { user } = useAuth();
    const section = currentSection();

    const [branchOptions, setBranchOptions] = useState([]);
    const [areaOptions, setAreaOptions] = useState([]);
    const [divisionOptions, setDivisionOptions] = useState([]);
    const [mrOptions, setMrOptions] = useState([]);
    const [customers, setCustomers] = useState([]);

    const canManage = user?.is_owner || can(user, 'mr.manage');
    const canVisits = canManage || can(user, 'mr.visits.manage');

    useEffect(() => {
        loadLookups();
    }, []);

    async function loadLookups() {
        try {
            const [{ data: br }, { data: mr }, { data: cu }, { data: areas }, { data: divisions }] = await Promise.all([
                http.get(endpoints.mrBranchOptions),
                http.get(endpoints.mrOptions),
                http.get(endpoints.customerOptions),
                http.get(endpoints.setupAreaOptions),
                http.get(endpoints.setupDivisionOptions),
            ]);

            setBranchOptions(br.data || []);
            setMrOptions(mr.data || []);
            setCustomers(cu.data || []);
            setAreaOptions(areas.data || []);
            setDivisionOptions(divisions.data || []);
        } catch {
            // silent
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                actions={(
                    <Space wrap>
                        {/* We removed the new branch/new MR/new Visit buttons from here because those open drawer states are now encapsulated within their respective panels. This cleans up the page header. */}
                    </Space>
                )}
            />

            {(section === 'dashboard' || section === 'performance') && (
                <MrDashboardPanel
                    section={section}
                    branchOptions={branchOptions}
                    mrOptions={mrOptions}
                />
            )}

            {section === 'representatives' && canManage && (
                <MrRepresentativesPanel
                    branchOptions={branchOptions}
                    areaOptions={areaOptions}
                    divisionOptions={divisionOptions}
                    canManage={canManage}
                />
            )}

            {section === 'visits' && canVisits && (
                <MrVisitsPanel
                    mrOptions={mrOptions}
                    customers={customers}
                    canVisits={canVisits}
                />
            )}

            {section === 'branches' && canManage && (
                <MrBranchesPanel
                    branchOptions={branchOptions}
                    canManage={canManage}
                    onLookupsChange={loadLookups}
                />
            )}
        </div>
    );
}
