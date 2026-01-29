import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import Studio from './Studio';
import type { ArchitectStudioProps } from './Studio';
import './studio.css';

declare global {
    interface Window {
        __ARCHITECT_PROPS__?: ArchitectStudioProps;
    }
}

const THEME_KEY = 'architect-theme';

function initTheme(): void {
    const stored = localStorage.getItem(THEME_KEY);
    if (stored === 'dark') {
        document.documentElement.classList.add('dark');
    } else if (stored === 'light') {
        document.documentElement.classList.remove('dark');
    } else if (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

initTheme();

const el = document.getElementById('architect-studio-root');
const props = window.__ARCHITECT_PROPS__;

if (el && props) {
    const root = createRoot(el);
    root.render(
        <StrictMode>
            <Studio {...props} standalone />
        </StrictMode>,
    );
}
