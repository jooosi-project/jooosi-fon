import { __ } from '@wordpress/i18n';
import { lazy, Suspense } from 'react';
import { createHashRouter, Navigate, RouterProvider } from 'react-router-dom';

import { AppShell } from '@/components/app-shell';

const FontsPage = lazy(() => import('@/pages/fonts-page').then((module) => ({
    default: module.FontsPage,
})));
const CustomFontEditorPage = lazy(() => import('@/pages/custom-font-editor-page').then((module) => ({ default: module.CustomFontEditorPage })));
const GoogleFontEditorPage = lazy(() => import('@/pages/google-font-editor-page').then((module) => ({ default: module.GoogleFontEditorPage })));
const SettingsPage = lazy(() => import('@/pages/settings-page').then((module) => ({ default: module.SettingsPage })));
const MigrationsPage = lazy(() => import('@/pages/migrations-page').then((module) => ({ default: module.MigrationsPage })));
const MigrationRunnerPage = lazy(() => import('@/pages/migrations-page').then((module) => ({ default: module.MigrationRunnerPage })));
const AboutPage = lazy(() => import('@/pages/about-page').then((module) => ({ default: module.AboutPage })));

function RouteLoading() {
    return (
        <div className="grid min-h-72 place-items-center text-sm text-muted-foreground" role="status">
            {__('Loading page…', 'jooosi-fon')}
        </div>
    );
}

export function App() {
    return <RouterProvider router={router} />;
}

const router = createHashRouter([
    {
        element: <AppShell />,
        children: [
            { index: true, element: <Navigate to="/fonts/index" replace /> },
            { path: '/fonts', element: <Navigate to="/fonts/index" replace /> },
            { path: '/fonts/index', element: <Suspense fallback={<RouteLoading />}><FontsPage /></Suspense> },
            { path: '/fonts/create/custom', element: <Suspense fallback={<RouteLoading />}><CustomFontEditorPage /></Suspense> },
            { path: '/fonts/create/google-fonts', element: <Suspense fallback={<RouteLoading />}><GoogleFontEditorPage /></Suspense> },
            { path: '/fonts/edit/:id/custom', element: <Suspense fallback={<RouteLoading />}><CustomFontEditorPage /></Suspense> },
            { path: '/fonts/edit/:id/google-fonts', element: <Suspense fallback={<RouteLoading />}><GoogleFontEditorPage /></Suspense> },
            { path: '/settings', element: <Suspense fallback={<RouteLoading />}><SettingsPage /></Suspense> },
            { path: '/about', element: <Suspense fallback={<RouteLoading />}><AboutPage /></Suspense> },
            { path: '/migrations', element: <Navigate to="/migrations/index" replace /> },
            { path: '/migrations/index', element: <Suspense fallback={<RouteLoading />}><MigrationsPage /></Suspense> },
            { path: '/migrations/custom-fonts-bricks', element: <Suspense fallback={<RouteLoading />}><MigrationRunnerPage sourceId="custom-fonts-bricks" /></Suspense> },
            { path: '/migrations/custom-fonts-brainstorm-force', element: <Suspense fallback={<RouteLoading />}><MigrationRunnerPage sourceId="custom-fonts-brainstorm-force" /></Suspense> },
            { path: '/migrations/custom-adobe-fonts', element: <Suspense fallback={<RouteLoading />}><MigrationRunnerPage sourceId="custom-adobe-fonts" /></Suspense> },
            { path: '/migrations/elementor-pro-custom-fonts', element: <Suspense fallback={<RouteLoading />}><MigrationRunnerPage sourceId="elementor-pro-custom-fonts" /></Suspense> },
            { path: '/migrations/font-hero-dplugins', element: <Suspense fallback={<RouteLoading />}><MigrationRunnerPage sourceId="font-hero-dplugins" /></Suspense> },
            { path: '/migrations/fonts-plugin', element: <Suspense fallback={<RouteLoading />}><MigrationRunnerPage sourceId="fonts-plugin" /></Suspense> },
            { path: '/migrations/use-any-font', element: <Suspense fallback={<RouteLoading />}><MigrationRunnerPage sourceId="use-any-font" /></Suspense> },
            { path: '*', element: <div className="grid min-h-96 place-items-center text-center"><div><h1 className="text-2xl font-semibold">{__('Page not found', 'jooosi-fon')}</h1><p className="text-sm text-muted-foreground">{__('The requested Jooosi Fon route could not be found.', 'jooosi-fon')}</p></div></div> },
        ],
    },
]);
