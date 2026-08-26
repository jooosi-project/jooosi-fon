import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';

import { App } from '@/app';
import { TooltipProvider } from '@/components/ui/tooltip';
import { isolateWordPressAdminStyles } from '@/lib/wp-admin-style-isolation';
import '@/styles/globals.css';

const container = document.getElementById('jooosi-fon-app');

if (!container) {
    throw new Error('Jooosi Fon mount element #jooosi-fon-app was not found.');
}

void isolateWordPressAdminStyles(container).then(() => {
    createRoot(container).render(
        <StrictMode>
            <TooltipProvider>
                <div className="jooosi-fon-ui">
                    <App />
                    <Toaster richColors closeButton position="bottom-right" />
                </div>
            </TooltipProvider>
        </StrictMode>,
    );
});
