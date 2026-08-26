import { __ } from '@wordpress/i18n';
import { useBlocker } from 'react-router-dom';
import { useCallback, useEffect, useRef } from 'react';

export function useUnsavedChanges(isDirty: boolean) {
    const bypassRef = useRef(false);
    const blocker = useBlocker(({ currentLocation, nextLocation }) => (
        isDirty && !bypassRef.current && currentLocation.pathname !== nextLocation.pathname
    ));

    useEffect(() => {
        const handler = (event: BeforeUnloadEvent) => {
            if (isDirty) event.preventDefault();
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [isDirty]);

    useEffect(() => {
        if (blocker.state !== 'blocked') return;
        if (window.confirm(__('You have unsaved changes. Leave this page?', 'jooosi-fon'))) blocker.proceed();
        else blocker.reset();
    }, [blocker]);

    return useCallback(() => {
        bypassRef.current = true;
    }, []);
}
