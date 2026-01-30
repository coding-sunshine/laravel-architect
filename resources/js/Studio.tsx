import { SchemaDiagram } from './components/SchemaDiagram';
import { belongsToFkColumn, modelToTableName } from './components/draftToFlow';
import type { ParsedDraft } from './components/draftToFlow';
import { Badge } from './components/ui/badge';
import { Button } from './components/ui/button';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    StudioCommandItem,
    CommandList,
} from './components/ui/command';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from './components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from './components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from './components/ui/dropdown-menu';
import { Input } from './components/ui/input';
import { Label } from './components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from './components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from './components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from './components/ui/tooltip';
import { Head } from '@inertiajs/react';
import { Check, Command as CommandIcon, ListTree, Loader2, MoonIcon, SunIcon } from 'lucide-react';
import { cn } from './lib/utils';
import yaml from 'js-yaml';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    DRAFT_HISTORY_KEY,
    DRAFT_HISTORY_MAX,
    LARAVEL_RELATIONSHIPS_URL,
    RELATION_LABELS,
    STARTER_SUMMARIES,
} from './constants';

function getSchemaSummary(draft: ParsedDraft | null): {
    modelCount: number;
    relationCount: number;
    relationList: string[];
    relationEntries: { label: string; edgeId: string }[];
    tableNames: string[];
    fkList: string[];
} {
    if (!draft?.models) return { modelCount: 0, relationCount: 0, relationList: [], relationEntries: [], tableNames: [], fkList: [] };
    const models = draft.models;
    const modelNames = Object.keys(models);
    const relationList: string[] = [];
    const relationEntries: { label: string; edgeId: string }[] = [];
    const fkList: string[] = [];
    for (const source of modelNames) {
        const rels = models[source].relationships;
        if (!rels || typeof rels !== 'object') continue;
        const relTypes = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany', 'morphTo', 'morphMany'] as const;
        for (const relType of relTypes) {
            const targetStr = rels[relType];
            if (!targetStr || typeof targetStr !== 'string') continue;
            const label = RELATION_LABELS[relType] ?? relType;
            const targetEntries = targetStr.split(',').map((t) => t.trim());
            for (const targetEntry of targetEntries) {
                const target = targetEntry.split(':')[0].trim();
                if (target && modelNames.includes(target)) {
                    const relLabel = `${source} ${label} ${target}`;
                    relationList.push(relLabel);
                    const edgeId = `${source}-${relType}-${target}-${targetEntry.replace(/:/g, '_')}`;
                    relationEntries.push({ label: relLabel, edgeId });
                    if (relType === 'belongsTo') {
                        fkList.push(`${modelToTableName(source)}.${belongsToFkColumn(targetEntry)}`);
                    }
                }
            }
        }
    }
    const tableNames = modelNames.map(modelToTableName);
    return { modelCount: modelNames.length, relationCount: relationList.length, relationList, relationEntries, tableNames, fkList };
}

/** Merge two draft objects (current + other); other wins on conflict. */
function mergeDraft(
    current: Record<string, unknown>,
    other: Record<string, unknown>,
): Record<string, unknown> {
    const out = { ...current };
    for (const key of Object.keys(other)) {
        const a = out[key];
        const b = other[key];
        if (a != null && b != null && typeof a === 'object' && typeof b === 'object' && !Array.isArray(a) && !Array.isArray(b)) {
            (out as Record<string, unknown>)[key] = mergeDraft(a as Record<string, unknown>, b as Record<string, unknown>);
        } else {
            (out as Record<string, unknown>)[key] = b;
        }
    }
    return out;
}

export interface SchemaHint {
    schema_key: string;
    description: string;
    requires_package: string;
    available: boolean;
}

export interface GeneratorVariants {
    stack: string;
    api_auth: string;
    test_framework: string;
    admin_panel: string;
    api_docs: string;
    broadcasting: string;
    features: Record<string, boolean>;
}

export interface ArchitectStudioProps {
    /** When true, do not render Inertia Head (for standalone Blade view). */
    standalone?: boolean;
    stack: string;
    packages: Array<{
        name: string;
        version: string;
        hints: {
            draft_extensions?: string[];
            generator_variants?: string[];
            suggested_commands?: string[];
        } | null;
    }>;
    existing_models: Array<{ name: string; table: string }>;
    draft_path: string;
    state_path: string;
    schema_version: string;
    ai_enabled: boolean;
    starters: string[];
    draft: string;
    variants?: GeneratorVariants;
    features?: Record<string, boolean>;
    schema_hints?: Record<string, SchemaHint>;
}

function getCsrfToken(): string | null {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));
    if (!cookie) return null;
    const value = cookie.split('=')[1];
    return value ? decodeURIComponent(value) : null;
}

export default function ArchitectStudio({
    standalone = false,
    stack,
    packages,
    existing_models,
    draft_path,
    ai_enabled,
    starters,
    draft: initialDraft,
    variants,
    features,
    schema_hints,
}: ArchitectStudioProps) {
    const [draftYaml, setDraftYaml] = useState(initialDraft ?? '');
    const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
    const [validateResult, setValidateResult] = useState<{
        valid: boolean;
        errors: string[];
    } | null>(null);
    const [planResult, setPlanResult] = useState<{
        steps: Array<{ type: string; name: string; description: string }>;
        summary: { models: number; actions: number; pages: number };
    } | null>(null);
    const [buildResult, setBuildResult] = useState<{
        success: boolean;
        generated: string[];
        errors: string[];
        warnings: string[];
    } | null>(null);
    const [resultPanelOpen, setResultPanelOpen] = useState(false);
    const [activeResultTab, setActiveResultTab] = useState<'validate' | 'plan' | 'build'>('validate');
    const [isValidating, setIsValidating] = useState(false);
    const [isPlanning, setIsPlanning] = useState(false);
    const [isBuilding, setIsBuilding] = useState(false);
    const [isDark, setIsDark] = useState(false);

    const [aiPanelOpen, setAiPanelOpen] = useState(false);
    const [aiDescription, setAiDescription] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiError, setAiError] = useState<string | null>(null);
    const [proposedYaml, setProposedYaml] = useState<string | null>(null);
    const [simpleSummary, setSimpleSummary] = useState<{ models: number; actions: number; pages: number } | null>(null);

    // AI Assistant Chat state
    const [aiChatOpen, setAiChatOpen] = useState(false);
    const [aiChatMessages, setAiChatMessages] = useState<Array<{ role: 'user' | 'assistant'; content: string }>>([]);
    const [aiChatInput, setAiChatInput] = useState('');
    const [aiChatLoading, setAiChatLoading] = useState(false);
    const [aiChatSuggestions, setAiChatSuggestions] = useState<string[]>([]);

    const [starterConfirmOpen, setStarterConfirmOpen] = useState(false);
    const [pendingStarter, setPendingStarter] = useState<{ name: string; yaml: string } | null>(null);

    const [importConfirmOpen, setImportConfirmOpen] = useState(false);
    const [importedYaml, setImportedYaml] = useState<string | null>(null);
    const [importLoading, setImportLoading] = useState(false);

    const [wizardOpen, setWizardOpen] = useState<'addModel' | 'addCrud' | 'addRelationship' | 'addPage' | null>(null);
    const [wizardLoading, setWizardLoading] = useState(false);
    const [wizardForm, setWizardForm] = useState<Record<string, string | boolean>>({});

    const [paletteOpen, setPaletteOpen] = useState(false);
    const [showYamlSplit, setShowYamlSplit] = useState(false);
    const [modelListPanelOpen, setModelListPanelOpen] = useState(false);
    const [focusNodeId, setFocusNodeId] = useState<string | null>(null);
    const [modelListFilter, setModelListFilter] = useState('');
    const [selectedRelationId, setSelectedRelationId] = useState<string | null>(null);
    const [showMinimap, setShowMinimap] = useState(true);
    const [previewCode, setPreviewCode] = useState<string | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewItem, setPreviewItem] = useState<{ type: string; name: string } | null>(null);
    const [featuresPanelOpen, setFeaturesPanelOpen] = useState(false);
    const fitViewRef = useRef<(() => void) | null>(null);

    // Compute available features count
    const availableFeaturesCount = useMemo(() => {
        if (!schema_hints) return 0;
        return Object.values(schema_hints).filter((h) => h.available).length;
    }, [schema_hints]);
    const totalFeaturesCount = schema_hints ? Object.keys(schema_hints).length : 0;
    const [draftHistory, setDraftHistory] = useState<string[]>(() => {
        try {
            const raw = localStorage.getItem(DRAFT_HISTORY_KEY);
            if (raw) {
                const parsed = JSON.parse(raw) as unknown;
                return Array.isArray(parsed) ? parsed.slice(0, DRAFT_HISTORY_MAX) : [];
            }
        } catch {
            // ignore
        }
        return [];
    });

    const parsedDraft = useMemo((): ParsedDraft | null => {
        if (!draftYaml.trim()) return null;
        try {
            const data = yaml.load(draftYaml) as unknown;
            if (data && typeof data === 'object' && 'models' in data) {
                return data as ParsedDraft;
            }
            return { models: {} };
        } catch {
            return null;
        }
    }, [draftYaml]);

    const parseError = useMemo((): string | null => {
        if (!draftYaml.trim()) return null;
        try {
            yaml.load(draftYaml);
            return null;
        } catch (e) {
            return e instanceof Error ? e.message : 'Invalid YAML';
        }
    }, [draftYaml]);

    const hasRelations = useMemo(() => {
        if (!parsedDraft?.models) return false;
        for (const def of Object.values(parsedDraft.models)) {
            const rels = def?.relationships;
            if (rels && typeof rels === 'object' && Object.keys(rels).length > 0) return true;
        }
        return false;
    }, [parsedDraft]);

    const schemaSummary = useMemo(() => getSchemaSummary(parsedDraft), [parsedDraft]);

    const modelNames = useMemo(
        () => (parsedDraft?.models ? Object.keys(parsedDraft.models) : []),
        [parsedDraft],
    );
    const filteredModelNames = useMemo(() => {
        const q = modelListFilter.toLowerCase().trim();
        if (!q) return modelNames;
        return modelNames.filter((n) => n.toLowerCase().includes(q));
    }, [modelNames, modelListFilter]);

    const pushDraftToHistory = useCallback((yamlContent: string) => {
        if (!yamlContent.trim()) return;
        setDraftHistory((prev) => {
            const next = [yamlContent, ...prev.filter((item) => item !== yamlContent)].slice(
                0,
                DRAFT_HISTORY_MAX,
            );
            try {
                localStorage.setItem(DRAFT_HISTORY_KEY, JSON.stringify(next));
            } catch {
                // ignore
            }
            return next;
        });
    }, []);

    useEffect(() => {
        if (!draftYaml.trim()) return;
        const t = setTimeout(() => pushDraftToHistory(draftYaml), 600);
        return () => clearTimeout(t);
    }, [draftYaml, pushDraftToHistory]);

    const apiFetch = useCallback(
        async (
            path: string,
            options: { method?: string; body?: string } = {},
        ): Promise<{ ok: boolean; data: unknown }> => {
            const csrf = getCsrfToken();
            const headers: Record<string, string> = {
                Accept: 'application/json',
            };
            if (options.body) headers['Content-Type'] = 'application/json';
            if (csrf) headers['X-XSRF-TOKEN'] = csrf;
            const res = await fetch(path, {
                credentials: 'same-origin',
                method: options.method ?? 'GET',
                body: options.body,
                headers,
            });
            const data = await res.json().catch(() => ({}));
            return { ok: res.ok, data };
        },
        [],
    );

    const fetchPreview = useCallback(async (type: string, name: string) => {
        setPreviewItem({ type, name });
        setPreviewLoading(true);
        try {
            const { ok, data } = await apiFetch(`/architect/api/preview?type=${type}&name=${encodeURIComponent(name)}`);
            const result = data as { code?: string };
            if (ok && result.code) {
                setPreviewCode(result.code);
            }
        } finally {
            setPreviewLoading(false);
        }
    }, [apiFetch]);

    const handleSave = useCallback(async () => {
        setSaveStatus('saving');
        const csrf = getCsrfToken();
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        };
        if (csrf) headers['X-XSRF-TOKEN'] = csrf;
        try {
            const res = await fetch('/architect/api/draft', {
                method: 'PUT',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({ yaml: draftYaml }),
            });
            const data = (await res.json()) as { valid?: boolean; errors?: string[] };
            if (res.ok && data.valid !== false) {
                setSaveStatus('saved');
                pushDraftToHistory(draftYaml);
                setTimeout(() => setSaveStatus('idle'), 2000);
            } else {
                setSaveStatus('error');
                setTimeout(() => setSaveStatus('idle'), 3000);
            }
        } catch {
            setSaveStatus('error');
            setTimeout(() => setSaveStatus('idle'), 3000);
        }
    }, [draftYaml, pushDraftToHistory]);

    const handleValidate = useCallback(async () => {
        setValidateResult(null);
        setActiveResultTab('validate');
        setResultPanelOpen(true);
        setIsValidating(true);
        try {
            const { ok, data } = await apiFetch('/architect/api/validate', {
                method: 'POST',
                body: JSON.stringify({ yaml: draftYaml }),
            });
            const result = data as { valid?: boolean; errors?: string[] };
            setValidateResult({
                valid: result.valid ?? false,
                errors: result.errors ?? (ok ? [] : ['Request failed']),
            });
        } finally {
            setIsValidating(false);
        }
    }, [apiFetch, draftYaml]);

    const handlePlan = useCallback(async () => {
        setPlanResult(null);
        setActiveResultTab('plan');
        setResultPanelOpen(true);
        setIsPlanning(true);
        try {
            const { ok, data } = await apiFetch('/architect/api/plan', {
                method: 'POST',
                body: JSON.stringify({}),
            });
            const result = data as {
                steps?: Array<{ type: string; name: string; description: string }>;
                summary?: { models: number; actions: number; pages: number };
                error?: string;
            };
            if (ok && result.steps) {
                setPlanResult({
                    steps: result.steps,
                    summary: result.summary ?? { models: 0, actions: 0, pages: 0 },
                });
            } else {
                setPlanResult({
                    steps: [],
                    summary: { models: 0, actions: 0, pages: 0 },
                });
                setValidateResult({
                    valid: false,
                    errors: [result.error ?? 'Plan failed'],
                });
            }
        } finally {
            setIsPlanning(false);
        }
    }, [apiFetch]);

    const handleBuild = useCallback(async () => {
        setBuildResult(null);
        setActiveResultTab('build');
        setResultPanelOpen(true);
        setIsBuilding(true);
        try {
            const { ok, data } = await apiFetch('/architect/api/build', {
                method: 'POST',
                body: JSON.stringify({}),
            });
            const result = data as {
                success?: boolean;
                generated?: string[];
                errors?: string[];
                warnings?: string[];
            };
            setBuildResult({
                success: result.success ?? false,
                generated: result.generated ?? [],
                errors: result.errors ?? [],
                warnings: result.warnings ?? [],
            });
        } finally {
            setIsBuilding(false);
        }
    }, [apiFetch]);

    const handleAiSubmit = useCallback(async () => {
        const desc = aiDescription.trim();
        if (!desc) return;
        setAiLoading(true);
        setAiError(null);
        setProposedYaml(null);
        setSimpleSummary(null);
        const { ok, data } = await apiFetch('/architect/api/draft-from-ai', {
            method: 'POST',
            body: JSON.stringify({ description: desc }),
        });
        setAiLoading(false);
        const result = data as { yaml?: string; error?: string };
        if (ok && result.yaml) {
            setProposedYaml(result.yaml);
        } else {
            setAiError(result.error ?? 'Failed to generate draft.');
        }
    }, [apiFetch, aiDescription]);

    const handleSimpleGenerate = useCallback(async () => {
        const desc = aiDescription.trim();
        if (!desc) return;
        setAiLoading(true);
        setAiError(null);
        setProposedYaml(null);
        setSimpleSummary(null);
        const { ok, data } = await apiFetch('/architect/api/simple-generate', {
            method: 'POST',
            body: JSON.stringify({ description: desc }),
        });
        setAiLoading(false);
        const result = data as { summary?: { models: number; actions: number; pages: number }; yaml?: string; error?: string };
        if (ok && result.yaml && result.summary) {
            setProposedYaml(result.yaml);
            setSimpleSummary(result.summary);
        } else {
            setAiError(result.error ?? 'Failed to generate draft.');
        }
    }, [apiFetch, aiDescription]);

    // AI Assistant Chat handlers
    const fetchChatSuggestions = useCallback(async () => {
        const { ok, data } = await apiFetch('/architect/api/ai/chat/suggestions');
        if (ok) {
            const result = data as { suggestions?: string[] };
            setAiChatSuggestions(result.suggestions ?? []);
        }
    }, [apiFetch]);

    const handleAiChat = useCallback(async (message?: string) => {
        const msg = message ?? aiChatInput.trim();
        if (!msg) return;
        setAiChatInput('');
        setAiChatMessages((prev) => [...prev, { role: 'user', content: msg }]);
        setAiChatLoading(true);
        try {
            const { ok, data } = await apiFetch('/architect/api/ai/chat', {
                method: 'POST',
                body: JSON.stringify({ message: msg, yaml: draftYaml }),
            });
            const result = data as { response?: string; suggestions?: string[]; actions?: Array<{ type: string; payload: string }> };
            if (ok && result.response) {
                setAiChatMessages((prev) => [...prev, { role: 'assistant', content: result.response ?? '' }]);
                if (result.suggestions) {
                    setAiChatSuggestions(result.suggestions);
                }
                // Handle actions if any (e.g., apply_yaml)
                if (result.actions) {
                    for (const action of result.actions) {
                        if (action.type === 'apply_yaml' && action.payload) {
                            setProposedYaml(action.payload);
                            setAiPanelOpen(true);
                        }
                    }
                }
            } else {
                setAiChatMessages((prev) => [...prev, { role: 'assistant', content: 'Sorry, I encountered an error. Please try again.' }]);
            }
        } catch {
            setAiChatMessages((prev) => [...prev, { role: 'assistant', content: 'Sorry, I encountered an error. Please try again.' }]);
        } finally {
            setAiChatLoading(false);
        }
    }, [apiFetch, aiChatInput, draftYaml]);

    useEffect(() => {
        if (aiChatOpen && aiChatSuggestions.length === 0) {
            fetchChatSuggestions();
        }
    }, [aiChatOpen, aiChatSuggestions.length, fetchChatSuggestions]);

    const applyProposed = useCallback(
        (action: 'apply' | 'edit' | 'discard') => {
            if (action === 'discard') {
                setProposedYaml(null);
                setSimpleSummary(null);
                setAiDescription('');
                setAiPanelOpen(false);
                return;
            }
            if (!proposedYaml) return;
            const yamlToSave = proposedYaml;
            setDraftYaml(yamlToSave);
            setProposedYaml(null);
            setSimpleSummary(null);
            setAiDescription('');
            setAiPanelOpen(false);
            if (action === 'apply') {
                setSaveStatus('saving');
                const csrf = getCsrfToken();
                const headers: Record<string, string> = {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                };
                if (csrf) (headers as Record<string, string>)['X-XSRF-TOKEN'] = csrf;
                fetch('/architect/api/draft', {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers,
                    body: JSON.stringify({ yaml: yamlToSave }),
                })
                    .then((res) => res.json())
                    .then(() => {
                        setSaveStatus('saved');
                        setTimeout(() => setSaveStatus('idle'), 2000);
                    })
                    .catch(() => {
                        setSaveStatus('error');
                        setTimeout(() => setSaveStatus('idle'), 3000);
                    });
            }
        },
        [proposedYaml],
    );

    const loadStarter = useCallback(
        async (name: string) => {
            const { ok, data } = await apiFetch(`/architect/api/starters/${encodeURIComponent(name)}`);
            const result = data as { yaml?: string; error?: string };
            if (ok && result.yaml) {
                setPendingStarter({ name, yaml: result.yaml });
                setStarterConfirmOpen(true);
            }
        },
        [apiFetch],
    );

    const confirmStarter = useCallback(
        (replace: boolean) => {
            if (!pendingStarter) return;
            if (replace) {
                setDraftYaml(pendingStarter.yaml);
            } else {
                try {
                    const current = (yaml.load(draftYaml) as Record<string, unknown>) ?? {};
                    const other = (yaml.load(pendingStarter.yaml) as Record<string, unknown>) ?? {};
                    const merged = mergeDraft(current, other);
                    setDraftYaml(yaml.dump(merged, { lineWidth: -1 }));
                } catch {
                    setDraftYaml(pendingStarter.yaml);
                }
            }
            setPendingStarter(null);
            setStarterConfirmOpen(false);
        },
        [pendingStarter, draftYaml],
    );

    const runImport = useCallback(async () => {
        setImportLoading(true);
        setImportedYaml(null);
        const { ok, data } = await apiFetch('/architect/api/import', { method: 'POST', body: '{}' });
        setImportLoading(false);
        if (ok && data && typeof data === 'object' && 'models' in data) {
            setImportedYaml(yaml.dump(data as object, { lineWidth: -1 }));
            setImportConfirmOpen(true);
        }
    }, [apiFetch]);

    const confirmImport = useCallback(
        (replace: boolean) => {
            if (!importedYaml) return;
            if (replace) {
                setDraftYaml(importedYaml);
            } else {
                try {
                    const current = (yaml.load(draftYaml) as Record<string, unknown>) ?? {};
                    const other = (yaml.load(importedYaml) as Record<string, unknown>) ?? {};
                    const merged = mergeDraft(current, other);
                    setDraftYaml(yaml.dump(merged, { lineWidth: -1 }));
                } catch {
                    setDraftYaml(importedYaml);
                }
            }
            setImportedYaml(null);
            setImportConfirmOpen(false);
        },
        [importedYaml, draftYaml],
    );

    useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement;
            const inInput =
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT';
            if (e.key === 'Escape') {
                setPaletteOpen(false);
                return;
            }
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setPaletteOpen((open) => !open);
                return;
            }
            if (paletteOpen) return;
            if (inInput) return;
            if (e.key === 'f' && !e.metaKey && !e.ctrlKey) {
                fitViewRef.current?.();
                e.preventDefault();
                return;
            }
            if (e.key === 'v' && !e.metaKey && !e.ctrlKey) {
                handleValidate();
                return;
            }
            if (e.key === 'p' && !e.metaKey && !e.ctrlKey) {
                handlePlan();
                return;
            }
            if (e.key === 'b' && !e.metaKey && !e.ctrlKey) {
                handleBuild();
                return;
            }
            if (e.key === 'a' && !e.metaKey && !e.ctrlKey && ai_enabled) {
                setAiChatOpen(true);
                return;
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [paletteOpen, handleValidate, handlePlan, handleBuild, ai_enabled]);

    useEffect(() => {
        if (standalone) {
            document.title = 'Architect Studio';
        }
    }, [standalone]);

    useEffect(() => {
        setIsDark(document.documentElement.classList.contains('dark'));
    }, []);

    const THEME_KEY = 'architect-theme';
    const toggleTheme = useCallback(() => {
        const root = document.documentElement;
        const next = !root.classList.contains('dark');
        if (next) {
            root.classList.add('dark');
            localStorage.setItem(THEME_KEY, 'dark');
        } else {
            root.classList.remove('dark');
            localStorage.setItem(THEME_KEY, 'light');
        }
        setIsDark(next);
    }, []);

    const knownPackages = packages.filter((p) => p.hints !== null);
    const knownPackagesCount = knownPackages.length;
    const truncatePath = (path: string, max = 40) =>
        path.length <= max ? path : path.slice(0, max - 3) + '...';

    return (
        <TooltipProvider>
            <div className="studio-bg-wrapper" aria-hidden="true" />
            <div className="relative flex h-screen flex-col">
                <header className="studio-header-glass flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-sidebar-border px-4 py-2">
                    <div className="flex items-center gap-2">
                        <span className="font-semibold">Architect Studio</span>
                        <Badge variant="secondary">{stack}</Badge>
                        {knownPackagesCount > 0 && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Badge variant="outline" className="cursor-help">
                                        {knownPackagesCount} known package
                                        {knownPackagesCount !== 1 ? 's' : ''}
                                    </Badge>
                                </TooltipTrigger>
                                <TooltipContent side="bottom" className="max-w-xs">
                                    <div className="space-y-1 text-xs">
                                        <div className="font-medium">Detected packages:</div>
                                        {knownPackages.map((pkg) => (
                                            <div key={pkg.name} className="flex items-center gap-2">
                                                <span className="font-mono text-muted-foreground">
                                                    {pkg.name}
                                                </span>
                                                <span className="text-muted-foreground/70">
                                                    {pkg.version}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {schema_hints && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Badge
                                        variant="outline"
                                        className="cursor-pointer hover:bg-accent"
                                        onClick={() => setFeaturesPanelOpen(true)}
                                    >
                                        {availableFeaturesCount}/{totalFeaturesCount} features
                                    </Badge>
                                </TooltipTrigger>
                                <TooltipContent side="bottom">
                                    Click to see available schema features
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2 md:gap-3">
                        <div className="hidden items-center gap-1.5 border-r border-sidebar-border pr-2 md:flex md:pr-3">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleSave}
                                        disabled={saveStatus === 'saving' || !draftYaml.trim()}
                                    >
                                        {saveStatus === 'saving' ? (
                                            <Loader2 className="size-4 animate-spin" />
                                        ) : saveStatus === 'saved' ? (
                                            <Check className="size-4 text-green-600 dark:text-green-400" />
                                        ) : null}
                                        {saveStatus === 'saving'
                                            ? 'Saving…'
                                            : saveStatus === 'saved'
                                              ? 'Saved'
                                              : saveStatus === 'error'
                                                ? 'Error'
                                                : 'Save draft'}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {saveStatus === 'saving'
                                        ? 'Saving draft to disk…'
                                        : saveStatus === 'saved'
                                          ? 'Draft saved.'
                                          : !draftYaml.trim()
                                            ? 'Add or paste a schema to save.'
                                            : 'Save the current draft to disk.'}
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handleValidate}
                                        disabled={!draftYaml.trim() || isValidating}
                                    >
                                        {isValidating ? <Loader2 className="size-4 animate-spin" /> : null}
                                        Check Schema
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {!draftYaml.trim()
                                        ? 'Add or paste a schema to validate.'
                                        : isValidating
                                          ? 'Checking schema…'
                                          : 'Check the draft for errors and Laravel conventions.'}
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePlan}
                                        disabled={!draftYaml.trim() || isPlanning}
                                    >
                                        {isPlanning ? <Loader2 className="size-4 animate-spin" /> : null}
                                        Preview Changes
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {!draftYaml.trim()
                                        ? 'Add or paste a schema to plan.'
                                        : isPlanning
                                          ? 'Generating plan…'
                                          : 'See what will be generated (migrations, models, actions, pages).'}
                                </TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        size="sm"
                                        onClick={handleBuild}
                                        disabled={!draftYaml.trim() || isBuilding}
                                    >
                                        {isBuilding ? <Loader2 className="size-4 animate-spin" /> : null}
                                        Generate Code
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {!draftYaml.trim()
                                        ? 'Add or paste a schema to build.'
                                        : isBuilding
                                          ? 'Generating files…'
                                          : 'Generate migrations, models, and code from this schema.'}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <DropdownMenu>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" size="sm" disabled={!parsedDraft}>
                                            Preview
                                        </Button>
                                    </DropdownMenuTrigger>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {!parsedDraft
                                        ? 'Add a valid schema to preview what will be generated.'
                                        : 'See a summary of models, tables, and relations that will be generated.'}
                                </TooltipContent>
                            </Tooltip>
                            <DropdownMenuContent align="start" className="max-h-[70vh] w-80 overflow-auto p-3 text-sm">
                                <p className="font-medium text-foreground">What you&apos;ll get</p>
                                {parsedDraft && schemaSummary.modelCount > 0 ? (
                                    <div className="mt-2 space-y-1.5 text-muted-foreground">
                                        <p>
                                            Models: {Object.keys(parsedDraft.models ?? {}).join(', ')}.
                                        </p>
                                        {schemaSummary.tableNames.length > 0 && (
                                            <p>
                                                Tables: {schemaSummary.tableNames.join(', ')}.
                                            </p>
                                        )}
                                        {schemaSummary.fkList.length > 0 && (
                                            <p>
                                                Foreign keys: {schemaSummary.fkList.join(', ')}.
                                            </p>
                                        )}
                                        {schemaSummary.relationList.length > 0 && (
                                            <p>
                                                Relations: {schemaSummary.relationList.join('; ')}.
                                            </p>
                                        )}
                                        {planResult && (
                                            <p>
                                                Plus {planResult.summary.models} migrations,{' '}
                                                {planResult.summary.actions} actions,{' '}
                                                {planResult.summary.pages} pages.
                                            </p>
                                        )}
                                        {!planResult && (
                                            <p className="text-xs">Run Plan to see full preview.</p>
                                        )}
                                    </div>
                                ) : (
                                    <p className="mt-2 text-muted-foreground">
                                        No schema yet. Describe your app, pick a template, or import from code.
                                    </p>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="shrink-0 md:hidden">
                                    Actions
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="max-h-[70vh] overflow-auto">
                                <DropdownMenuItem
                                    onClick={handleValidate}
                                    disabled={!draftYaml.trim()}
                                >
                                    Validate
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={handlePlan}
                                    disabled={!draftYaml.trim()}
                                >
                                    Plan
                                </DropdownMenuItem>
                                {starters.length > 0 &&
                                    starters.map((name) => (
                                        <DropdownMenuItem
                                            key={name}
                                            onClick={() => loadStarter(name)}
                                        >
                                            Start from template: {name}
                                        </DropdownMenuItem>
                                    ))}
                                <DropdownMenuItem
                                    onClick={runImport}
                                    disabled={importLoading}
                                >
                                    Import from codebase
                                </DropdownMenuItem>
                                <div className="my-1 border-t border-sidebar-border" />
                                <DropdownMenuItem onClick={() => setShowYamlSplit((v) => !v)}>
                                    {showYamlSplit ? 'Diagram only' : 'YAML split'}
                                </DropdownMenuItem>
                                {draftHistory.length > 0 &&
                                    draftHistory.map((y, i) => (
                                        <DropdownMenuItem
                                            key={i}
                                            onClick={() => setDraftYaml(y)}
                                        >
                                            History: Restore #{i + 1}
                                        </DropdownMenuItem>
                                    ))}
                                <DropdownMenuItem onClick={() => setPaletteOpen(true)}>
                                    Search actions (⌘K)
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="hidden md:inline-flex">
                                    Templates
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {starters.length > 0 && (
                                    <>
                                        {starters.map((name) => (
                                            <DropdownMenuItem
                                                key={name}
                                                onClick={() => loadStarter(name)}
                                            >
                                                <span>Start from template: {name}</span>
                                                {STARTER_SUMMARIES[name] && (
                                                    <span className="ml-2 text-muted-foreground">— {STARTER_SUMMARIES[name]}</span>
                                                )}
                                            </DropdownMenuItem>
                                        ))}
                                        <div className="my-1 border-t border-sidebar-border" />
                                    </>
                                )}
                                <DropdownMenuItem
                                    onClick={runImport}
                                    disabled={importLoading}
                                >
                                    Import from codebase
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <div className="hidden items-center gap-1.5 border-r border-sidebar-border pr-2 md:flex md:pr-3">
                            <Button
                                variant={modelListPanelOpen ? 'secondary' : 'outline'}
                                size="sm"
                                onClick={() => setModelListPanelOpen((v) => !v)}
                                title="Toggle model list"
                            >
                                <ListTree className="size-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowYamlSplit((v) => !v)}
                            >
                                {showYamlSplit ? 'Diagram only' : 'YAML'}
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        History
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {draftHistory.length === 0 ? (
                                        <DropdownMenuItem disabled>No history yet</DropdownMenuItem>
                                    ) : (
                                        draftHistory.map((y, i) => (
                                            <DropdownMenuItem
                                                key={i}
                                                onClick={() => setDraftYaml(y)}
                                            >
                                                Restore #{i + 1}
                                            </DropdownMenuItem>
                                        ))
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" className="hidden md:inline-flex">
                                    New feature
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => { setWizardOpen('addModel'); setWizardForm({}); }}>
                                    Add model
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => { setWizardOpen('addCrud'); setWizardForm({}); }}>
                                    Add CRUD resource
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => { setWizardOpen('addRelationship'); setWizardForm({}); }}>
                                    Add relationship
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => { setWizardOpen('addPage'); setWizardForm({}); }}>
                                    Add page
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        {ai_enabled && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="hidden md:inline-flex"
                                    onClick={() => {
                                        setAiError(null);
                                        setProposedYaml(null);
                                        setAiPanelOpen(true);
                                    }}
                                >
                                    Describe with AI
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="hidden md:inline-flex"
                                    onClick={() => setAiChatOpen(true)}
                                >
                                    AI Assistant
                                </Button>
                            </>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            className="hidden md:inline-flex"
                            onClick={() => setPaletteOpen(true)}
                            title="Search actions (⌘K)"
                        >
                            <CommandIcon className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={toggleTheme}
                            title={isDark ? 'Switch to light' : 'Switch to dark'}
                        >
                            {isDark ? (
                                <SunIcon className="size-4" />
                            ) : (
                                <MoonIcon className="size-4" />
                            )}
                        </Button>
                    </div>
                </header>
                <div className="studio-context-strip shrink-0 border-b border-sidebar-border px-4 py-1.5">
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <div className="flex items-center gap-1 font-medium">
                            <button
                                onClick={() => fitViewRef.current?.()}
                                className={cn(
                                    "hover:text-foreground transition-colors",
                                    draftYaml.trim() ? "text-primary" : "text-muted-foreground"
                                )}
                            >
                                Design
                            </button>
                            <span className="opacity-30">→</span>
                            <button
                                onClick={handleValidate}
                                className={cn(
                                    "hover:text-foreground transition-colors",
                                    validateResult?.valid ? "text-primary" : "text-muted-foreground"
                                )}
                            >
                                Check
                            </button>
                            <span className="opacity-30">→</span>
                            <button
                                onClick={handlePlan}
                                className={cn(
                                    "hover:text-foreground transition-colors",
                                    planResult ? "text-primary" : "text-muted-foreground"
                                )}
                            >
                                Preview
                            </button>
                            <span className="opacity-30">→</span>
                            <button
                                onClick={handleBuild}
                                className={cn(
                                    "hover:text-foreground transition-colors",
                                    buildResult?.success ? "text-primary" : "text-muted-foreground"
                                )}
                            >
                                Generate
                            </button>
                        </div>
                        <span className="text-muted-foreground/60">·</span>
                        {parseError && (
                            <span className="text-destructive" title={parseError}>
                                Invalid YAML{parseError.length > 0 ? `: ${parseError.slice(0, 50)}${parseError.length > 50 ? '…' : ''}` : ''}
                            </span>
                        )}
                        {validateResult !== null && (
                            <span
                                className={
                                    validateResult.valid
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-amber-600 dark:text-amber-400'
                                }
                            >
                                {validateResult.valid
                                    ? 'Draft valid'
                                    : `${validateResult.errors.length} error${validateResult.errors.length !== 1 ? 's' : ''}`}
                            </span>
                        )}
                        <span>AI: {ai_enabled ? 'on' : 'off'}</span>
                        <span title={draft_path}>{truncatePath(draft_path)}</span>
                        <span>{existing_models.length} models</span>
                        {starters.length > 0 && (
                            <span>Starters: {starters.join(', ')}</span>
                        )}
                        <a
                            href={LARAVEL_RELATIONSHIPS_URL}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary hover:underline flex items-center gap-1"
                        >
                            Laravel relationships
                            <CommandIcon className="size-3 opacity-50" />
                        </a>
                    </div>
                    {parsedDraft && schemaSummary.modelCount > 0 && (
                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span>
                                {schemaSummary.modelCount} model{schemaSummary.modelCount !== 1 ? 's' : ''} ·{' '}
                                {schemaSummary.relationCount} relation{schemaSummary.relationCount !== 1 ? 's' : ''}
                            </span>
                            {schemaSummary.relationEntries.length > 0 &&
                                schemaSummary.relationEntries.slice(0, 5).map((entry) => (
                                    <button
                                        key={entry.edgeId}
                                        type="button"
                                        onClick={() => setSelectedRelationId(selectedRelationId === entry.edgeId ? null : entry.edgeId)}
                                        className={`rounded px-1.5 py-0.5 font-mono hover:bg-muted/70 ${selectedRelationId === entry.edgeId ? 'ring-1 ring-primary bg-primary/10' : 'bg-muted/50'}`}
                                    >
                                        {entry.label}
                                    </button>
                                ))}
                            {schemaSummary.relationEntries.length > 5 && (
                                <span>+{schemaSummary.relationEntries.length - 5} more</span>
                            )}
                        </div>
                    )}
                </div>
                <main className="relative flex min-h-0 flex-1 flex-col gap-4 overflow-hidden p-4">
                    <div
                        className={
                            showYamlSplit
                                ? 'grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-2'
                                : 'min-h-0 flex-1'
                        }
                    >
                        <div className="flex min-h-0 flex-1 gap-4">
                            {modelListPanelOpen && (
                                <div className="flex w-64 shrink-0 flex-col gap-4 rounded-2xl studio-card p-4 overflow-hidden">
                                    <div className="space-y-1">
                                        <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider px-2">Project Outline</h3>
                                        <Input
                                            placeholder="Filter..."
                                            value={modelListFilter}
                                            onChange={(e) => setModelListFilter(e.target.value)}
                                            className="h-8 text-xs bg-background/50"
                                        />
                                    </div>
                                    
                                    <div className="flex-1 overflow-auto space-y-6 pr-2 custom-scrollbar">
                                        {/* Models Section */}
                                        <div className="space-y-1">
                                            <div className="flex items-center justify-between px-2">
                                                <span className="text-sm font-medium">Models</span>
                                                <Badge variant="outline" className="text-[10px] h-4">{modelNames.length}</Badge>
                                            </div>
                                            <ul className="space-y-0.5">
                                                {filteredModelNames.map((name) => (
                                                    <li key={name}>
                                                        <button
                                                            type="button"
                                                            className={cn(
                                                                "w-full rounded-lg px-2 py-1.5 text-left text-sm hover:bg-primary/10 transition-colors group",
                                                                focusNodeId === name && "bg-primary/10 text-primary"
                                                            )}
                                                            onClick={() => {
                                                                setFocusNodeId(name);
                                                                fetchPreview('model', name);
                                                            }}
                                                        >
                                                            <span className="block font-medium truncate">{name}</span>
                                                            <span className="block text-[10px] text-muted-foreground group-hover:text-primary/70 transition-colors">table: {modelToTableName(name)}</span>
                                                        </button>
                                                    </li>
                                                ))}
                                                {modelNames.length === 0 && (
                                                    <li className="px-2 py-4 text-center border border-dashed border-sidebar-border rounded-lg">
                                                        <p className="text-xs text-muted-foreground">No models yet</p>
                                                    </li>
                                                )}
                                            </ul>
                                        </div>

                                        {/* Actions Section */}
                                        <div className="space-y-1">
                                            <div className="flex items-center justify-between px-2">
                                                <span className="text-sm font-medium">Actions</span>
                                                <Badge variant="outline" className="text-[10px] h-4">
                                                    {Object.keys(parsedDraft?.actions ?? {}).length}
                                                </Badge>
                                            </div>
                                            <ul className="space-y-0.5">
                                                {Object.keys(parsedDraft?.actions ?? {}).map((name) => (
                                                    <li key={name}>
                                                        <button
                                                            type="button"
                                                            className={cn(
                                                                "w-full rounded-lg px-2 py-1.5 text-left text-sm hover:bg-primary/10 transition-colors group",
                                                                previewItem?.name === name && previewItem?.type === 'action' && "bg-primary/10 text-primary"
                                                            )}
                                                            onClick={() => fetchPreview('action', name)}
                                                        >
                                                            <span className="block font-medium truncate text-foreground/80 group-hover:text-primary/70 transition-colors">{name}</span>
                                                        </button>
                                                    </li>
                                                ))}
                                                {Object.keys(parsedDraft?.actions ?? {}).length === 0 && (
                                                    <li className="px-2 py-4 text-center border border-dashed border-sidebar-border rounded-lg">
                                                        <p className="text-xs text-muted-foreground">No actions defined</p>
                                                    </li>
                                                )}
                                            </ul>
                                        </div>

                                        {/* Pages Section */}
                                        <div className="space-y-1">
                                            <div className="flex items-center justify-between px-2">
                                                <span className="text-sm font-medium">Pages</span>
                                                <Badge variant="outline" className="text-[10px] h-4">
                                                    {Object.keys(parsedDraft?.pages ?? {}).length}
                                                </Badge>
                                            </div>
                                            <ul className="space-y-0.5">
                                                {Object.keys(parsedDraft?.pages ?? {}).map((name) => (
                                                    <li key={name}>
                                                        <div className="w-full rounded-lg px-2 py-1.5 text-left text-sm text-muted-foreground">
                                                            <span className="block font-medium truncate text-foreground/80">{name}</span>
                                                        </div>
                                                    </li>
                                                ))}
                                                {Object.keys(parsedDraft?.pages ?? {}).length === 0 && (
                                                    <li className="px-2 py-4 text-center border border-dashed border-sidebar-border rounded-lg">
                                                        <p className="text-xs text-muted-foreground">No pages defined</p>
                                                    </li>
                                                )}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            )}
                        <div className="studio-card relative z-10 flex min-h-0 flex-1 flex-col rounded-2xl p-6">
                            <div className="min-h-[300px] flex-1 w-full">
                                {!draftYaml.trim() ? (
                                    <div className="flex h-full min-h-[400px] flex-col items-center justify-center rounded-2xl border border-dashed border-sidebar-border bg-white/20 dark:bg-black/20 p-12 text-center shadow-inner overflow-auto">
                                        <div className="max-w-2xl w-full space-y-8">
                                            <div className="space-y-4">
                                                <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                                    Ready to build your next app?
                                                </h2>
                                                <p className="text-lg text-muted-foreground leading-relaxed">
                                                    Architect turns your ideas into working Laravel code. 
                                                    Design your schema visually or with AI, then generate everything you need.
                                                </p>
                                            </div>

                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-left">
                                                <div className="p-4 rounded-xl bg-background/40 border border-sidebar-border space-y-2">
                                                    <div className="size-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                                                        <CommandIcon className="size-4" />
                                                    </div>
                                                    <h3 className="font-semibold text-sm">Visualize</h3>
                                                    <p className="text-xs text-muted-foreground leading-snug">See your database relationships in real-time as you design.</p>
                                                </div>
                                                <div className="p-4 rounded-xl bg-background/40 border border-sidebar-border space-y-2">
                                                    <div className="size-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                                                        <Loader2 className="size-4" />
                                                    </div>
                                                    <h3 className="font-semibold text-sm">Automate</h3>
                                                    <p className="text-xs text-muted-foreground leading-snug">Generate migrations, models, and actions in one click.</p>
                                                </div>
                                                <div className="p-4 rounded-xl bg-background/40 border border-sidebar-border space-y-2">
                                                    <div className="size-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                                                        <MoonIcon className="size-4" />
                                                    </div>
                                                    <h3 className="font-semibold text-sm">AI-Powered</h3>
                                                    <p className="text-xs text-muted-foreground leading-snug">Go from an idea to a working feature using natural language.</p>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center justify-center gap-4">
                                                {ai_enabled && (
                                                    <Button
                                                        size="lg"
                                                        className="h-12 px-8 text-base font-semibold shadow-lg hover:shadow-xl transition-all"
                                                        onClick={() => {
                                                            setAiError(null);
                                                            setProposedYaml(null);
                                                            setAiPanelOpen(true);
                                                        }}
                                                    >
                                                        Describe with AI
                                                    </Button>
                                                )}
                                                {starters.length > 0 && (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="outline" size="lg" className="h-12 px-8 text-base font-medium">
                                                                Start from template
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent className="w-56">
                                                            {starters.map((name) => (
                                                                <DropdownMenuItem
                                                                    key={name}
                                                                    onClick={() => loadStarter(name)}
                                                                    className="py-2"
                                                                >
                                                                    <span className="font-medium">{name}</span>
                                                                    {STARTER_SUMMARIES[name] && (
                                                                        <span className="ml-2 text-xs text-muted-foreground">— {STARTER_SUMMARIES[name]}</span>
                                                                    )}
                                                                </DropdownMenuItem>
                                                            ))}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                )}
                                                <Button variant="outline" size="lg" className="h-12 px-8 text-base font-medium" onClick={runImport}>
                                                    Import from codebase
                                                </Button>
                                            </div>

                                            <div className="space-y-3 pt-4">
                                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">Quick Start Recipes</p>
                                                <div className="flex flex-wrap justify-center gap-2">
                                                    <button 
                                                        onClick={() => {
                                                            setAiDescription("Create a blog with Post and Comment; each Post has many Comments, each Comment belongs to a User.");
                                                            setAiPanelOpen(true);
                                                        }}
                                                        className="px-3 py-1.5 rounded-full bg-background/60 border border-sidebar-border text-xs hover:bg-background transition-colors"
                                                    >
                                                        🚀 Blog with Comments
                                                    </button>
                                                    <button 
                                                        onClick={() => {
                                                            setAiDescription("A SaaS with Team, Project, and Task; Team has many Projects, Project has many Tasks.");
                                                            setAiPanelOpen(true);
                                                        }}
                                                        className="px-3 py-1.5 rounded-full bg-background/60 border border-sidebar-border text-xs hover:bg-background transition-colors"
                                                    >
                                                        👥 Team Management
                                                    </button>
                                                    <button 
                                                        onClick={() => {
                                                            setAiDescription("An e-commerce with Product, Category, and Order; Product belongs to many Categories.");
                                                            setAiPanelOpen(true);
                                                        }}
                                                        className="px-3 py-1.5 rounded-full bg-background/60 border border-sidebar-border text-xs hover:bg-background transition-colors"
                                                    >
                                                        🛒 Shop Schema
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <SchemaDiagram
                                            draft={parsedDraft}
                                            focusNodeId={focusNodeId}
                                            onFocusDone={() => setFocusNodeId(null)}
                                            highlightEdgeId={selectedRelationId}
                                            showMinimap={showMinimap}
                                            fitViewRef={fitViewRef}
                                        />
                                        {!parsedDraft && (
                                            <p className="mt-2 text-sm text-destructive">
                                                Invalid YAML. Fix the draft to see the diagram.
                                            </p>
                                        )}
                                        {parsedDraft &&
                                            Object.keys(parsedDraft.models ?? {}).length > 0 &&
                                            !hasRelations && (
                                                <p className="mt-2 text-muted-foreground text-sm">
                                                    No relations yet. Add relationships in the draft
                                                    YAML (e.g. relationships.belongsTo, hasMany).
                                                </p>
                                            )}
                                    </>
                                )}
                            </div>
                        </div>
                        {previewCode && (
                            <div className="w-80 shrink-0 flex flex-col gap-2 rounded-2xl studio-card p-4 overflow-hidden animate-in slide-in-from-right duration-300">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Code Preview</h3>
                                    <Button variant="ghost" size="sm" className="h-6 w-6 p-0" onClick={() => setPreviewCode(null)}>×</Button>
                                </div>
                                <div className="flex items-center gap-2 px-1">
                                    <Badge variant="secondary" className="text-[10px]">{previewItem?.type}</Badge>
                                    <span className="text-xs font-mono truncate">{previewItem?.name}.php</span>
                                </div>
                                <div className="flex-1 bg-background/50 rounded-lg border border-sidebar-border p-3 overflow-auto custom-scrollbar">
                                    {previewLoading ? (
                                        <div className="h-full flex items-center justify-center">
                                            <Loader2 className="size-6 animate-spin text-muted-foreground" />
                                        </div>
                                    ) : (
                                        <pre className="text-[10px] font-mono leading-relaxed text-foreground/80">
                                            {previewCode}
                                        </pre>
                                    )}
                                </div>
                                <p className="text-[10px] text-muted-foreground italic px-1">
                                    This is a read-only preview of the generated Laravel code.
                                </p>
                            </div>
                        )}
                        </div>
                        {showYamlSplit && (
                            <div className="flex min-h-0 flex-col rounded-lg border border-sidebar-border bg-card p-4">
                                <p className="mb-2 font-medium">Draft YAML</p>
                                <textarea
                                    className="min-h-[300px] flex-1 font-mono text-sm rounded-md border border-input bg-background p-3 ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    value={draftYaml}
                                    onChange={(e) => setDraftYaml(e.target.value)}
                                    spellCheck={false}
                                />
                            </div>
                        )}
                    </div>
                    <Collapsible
                        open={resultPanelOpen}
                        onOpenChange={setResultPanelOpen}
                        className="studio-results-panel shrink-0 border border-sidebar-border bg-card"
                    >
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between px-4 py-2 text-left font-medium hover:bg-muted/50"
                            >
                                Results
                                {(validateResult ?? planResult ?? buildResult) && (
                                    <Badge
                                        variant={
                                            validateResult && !validateResult.valid
                                                ? 'destructive'
                                                : buildResult && !buildResult.success
                                                  ? 'destructive'
                                                  : 'secondary'
                                        }
                                    >
                                        {validateResult && (
                                            <>Validate: {validateResult.valid ? 'OK' : 'Errors'}</>
                                        )}
                                        {planResult && !validateResult && (
                                            <>Plan: {planResult.steps.length} steps</>
                                        )}
                                        {buildResult && !validateResult && !planResult && (
                                            <>
                                                Build: {buildResult.success ? 'OK' : 'Errors'}
                                                {buildResult.generated?.length > 0 &&
                                                    ` (${buildResult.generated.length} files)`}
                                            </>
                                        )}
                                    </Badge>
                                )}
                            </button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <Tabs
                                value={activeResultTab}
                                onValueChange={(v) =>
                                    setActiveResultTab(v as 'validate' | 'plan' | 'build')
                                }
                                className="w-full"
                            >
                                <TabsList className="w-full justify-start rounded-none border-b border-sidebar-border bg-transparent p-0">
                                    <TabsTrigger
                                        value="validate"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:shadow-none"
                                    >
                                        Check Schema
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="plan"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:shadow-none"
                                    >
                                        Preview Changes
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="build"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:shadow-none"
                                    >
                                        Generate Code
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="validate" className="max-h-48 overflow-auto px-4 py-2 text-sm">
                                    <p className="mb-2 text-muted-foreground">
                                        Check your draft for errors and Laravel conventions.
                                    </p>
                                    {validateResult ? (
                                        <div className="space-y-2">
                                            <p className="font-medium">
                                                {validateResult.valid
                                                    ? 'Draft is valid.'
                                                    : `${validateResult.errors.length} issue${validateResult.errors.length !== 1 ? 's' : ''} found.`}
                                            </p>
                                            {validateResult.errors.length > 0 && (
                                                <ul className="list-inside list-disc text-destructive">
                                                    {validateResult.errors.map((e, i) => (
                                                        <li key={i}>{e}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            Run Validate to see results.
                                        </p>
                                    )}
                                </TabsContent>
                                <TabsContent value="plan" className="max-h-48 overflow-auto px-4 py-2 text-sm">
                                    <p className="mb-2 text-muted-foreground">
                                        See what will be generated: migrations, models, actions, pages.
                                    </p>
                                    {planResult && planResult.steps.length > 0 ? (
                                        <div className="space-y-2">
                                            <p className="text-muted-foreground">
                                                This will create {planResult.summary.models} model
                                                {planResult.summary.models !== 1 ? 's' : ''},{' '}
                                                {planResult.summary.actions} actions, and{' '}
                                                {planResult.summary.pages} page
                                                {planResult.summary.pages !== 1 ? 's' : ''}.
                                                {schemaSummary.relationList.length > 0 && (
                                                    <> Relations: {schemaSummary.relationList.join('; ')}.</>
                                                )}
                                            </p>
                                            <p className="font-medium">
                                                Plan: {planResult.summary.models} models,{' '}
                                                {planResult.summary.actions} actions,{' '}
                                                {planResult.summary.pages} pages
                                            </p>
                                            <ul className="list-inside list-disc text-muted-foreground">
                                                {planResult.steps.slice(0, 10).map((s, i) => (
                                                    <li key={i}>{s.description}</li>
                                                ))}
                                                {planResult.steps.length > 10 && (
                                                    <li>+{planResult.steps.length - 10} more</li>
                                                )}
                                            </ul>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            Run Plan to see results.
                                        </p>
                                    )}
                                </TabsContent>
                                <TabsContent value="build" className="max-h-48 overflow-auto px-4 py-2 text-sm">
                                    <p className="mb-2 text-muted-foreground">
                                        Generate files from your schema. Run Build to create migrations and code.
                                    </p>
                                    {buildResult ? (
                                        <div className="space-y-4">
                                            <div className="space-y-2">
                                                <p className="font-medium text-green-600 dark:text-green-400 flex items-center gap-2">
                                                    <Check className="size-4" />
                                                    {buildResult.success
                                                        ? `Generated ${buildResult.generated.length} file${buildResult.generated.length !== 1 ? 's' : ''}.`
                                                        : 'Build failed.'}
                                                </p>
                                                {buildResult.generated.length > 0 && (
                                                    <div className="p-3 rounded-lg bg-muted/50 border border-sidebar-border space-y-2">
                                                        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">What&apos;s Next?</p>
                                                        <ul className="space-y-1.5">
                                                            <li className="flex items-start gap-2 text-xs">
                                                                <span className="mt-0.5 size-1.5 rounded-full bg-primary shrink-0" />
                                                                <span>Run <code className="px-1 py-0.5 rounded bg-background border border-sidebar-border font-mono">php artisan migrate</code> in your terminal.</span>
                                                            </li>
                                                            <li className="flex items-start gap-2 text-xs">
                                                                <span className="mt-0.5 size-1.5 rounded-full bg-primary shrink-0" />
                                                                <span>Check <code className="px-1 py-0.5 rounded bg-background border border-sidebar-border font-mono">app/Models</code> for your new classes.</span>
                                                            </li>
                                                            {buildResult.generated.some(f => f.includes('Http/Controllers')) && (
                                                                <li className="flex items-start gap-2 text-xs">
                                                                    <span className="mt-0.5 size-1.5 rounded-full bg-primary shrink-0" />
                                                                    <span>Review the new controllers in <code className="px-1 py-0.5 rounded bg-background border border-sidebar-border font-mono">app/Http/Controllers</code>.</span>
                                                                </li>
                                                            )}
                                                        </ul>
                                                    </div>
                                                )}
                                            </div>
                                            {buildResult.errors.length > 0 && (
                                                <ul className="list-inside list-disc text-destructive">
                                                    {buildResult.errors.map((e, i) => (
                                                        <li key={i}>{e}</li>
                                                    ))}
                                                </ul>
                                            )}
                                            {buildResult.warnings.length > 0 && (
                                                <ul className="list-inside list-disc text-amber-600 dark:text-amber-400">
                                                    {buildResult.warnings.map((e, i) => (
                                                        <li key={i}>{e}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            Run Build to see results.
                                        </p>
                                    )}
                                </TabsContent>
                            </Tabs>
                        </CollapsibleContent>
                    </Collapsible>
                </main>
            </div>

            <Sheet open={aiPanelOpen} onOpenChange={setAiPanelOpen}>
                <SheetContent className="flex flex-col sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Describe with AI</SheetTitle>
                        <SheetDescription>
                            Describe your app or change; AI will propose a draft you can apply or edit.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-1 flex-col gap-4 py-4">
                        {!proposedYaml ? (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="ai-desc">Description</Label>
                                    <textarea
                                        id="ai-desc"
                                        className="min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="e.g. A blog with Post and Comment; each Post has many Comments, each Comment belongs to a User. Laravel conventions: singular model names, relationships create tables and foreign keys (posts.user_id)."
                                        value={aiDescription}
                                        onChange={(e) => setAiDescription(e.target.value)}
                                        disabled={aiLoading}
                                    />
                                </div>
                                {aiError && (
                                    <p className="text-destructive text-sm">{aiError}</p>
                                )}
                            </>
                        ) : (
                            <div className="flex flex-1 flex-col gap-2">
                                {simpleSummary && (
                                    <p className="text-sm text-muted-foreground">
                                        {simpleSummary.models} model(s), {simpleSummary.actions} action(s), {simpleSummary.pages} page(s)
                                    </p>
                                )}
                                <Label>Proposed draft</Label>
                                <pre className="max-h-[280px] flex-1 overflow-auto rounded-md border border-sidebar-border bg-muted/50 p-3 text-xs">
                                    {proposedYaml}
                                </pre>
                            </div>
                        )}
                    </div>
                    <SheetFooter>
                        {!proposedYaml ? (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setAiPanelOpen(false);
                                        setAiError(null);
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={handleSimpleGenerate}
                                    disabled={aiLoading || !aiDescription.trim()}
                                >
                                    {aiLoading ? 'Generating…' : 'Simple'}
                                </Button>
                                <Button
                                    onClick={handleAiSubmit}
                                    disabled={aiLoading || !aiDescription.trim()}
                                >
                                    {aiLoading ? 'Generating…' : 'Generate'}
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button variant="outline" onClick={() => applyProposed('discard')}>
                                    Discard
                                </Button>
                                <Button variant="outline" onClick={() => applyProposed('edit')}>
                                    Edit
                                </Button>
                                <Button onClick={() => applyProposed('apply')}>Apply</Button>
                            </>
                        )}
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            {/* AI Chat Assistant Panel */}
            <Sheet open={aiChatOpen} onOpenChange={setAiChatOpen}>
                <SheetContent className="flex flex-col sm:max-w-lg" side="right">
                    <SheetHeader>
                        <SheetTitle>AI Assistant</SheetTitle>
                        <SheetDescription>
                            Ask questions about your schema, get suggestions, or request code generation.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-1 flex-col gap-4 py-4 overflow-hidden">
                        {/* Chat Messages */}
                        <div className="flex-1 overflow-y-auto space-y-3 min-h-0">
                            {aiChatMessages.length === 0 && !aiChatLoading && (
                                <div className="text-center text-muted-foreground text-sm py-8">
                                    <p className="mb-4">I can help you with:</p>
                                    <ul className="text-left space-y-2 text-xs">
                                        <li>• Designing your database schema</li>
                                        <li>• Suggesting fields and relationships</li>
                                        <li>• Recommending packages for features</li>
                                        <li>• Validating your schema</li>
                                        <li>• Generating code snippets</li>
                                    </ul>
                                </div>
                            )}
                            {aiChatMessages.map((msg, idx) => (
                                <div
                                    key={idx}
                                    className={cn(
                                        "rounded-lg px-3 py-2 text-sm max-w-[85%]",
                                        msg.role === 'user'
                                            ? "ml-auto bg-primary text-primary-foreground"
                                            : "bg-muted"
                                    )}
                                >
                                    <div className="whitespace-pre-wrap">{msg.content}</div>
                                </div>
                            ))}
                            {aiChatLoading && (
                                <div className="flex items-center gap-2 text-muted-foreground text-sm">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Thinking...
                                </div>
                            )}
                        </div>

                        {/* Quick Suggestions */}
                        {aiChatSuggestions.length > 0 && !aiChatLoading && (
                            <div className="flex flex-wrap gap-2">
                                {aiChatSuggestions.slice(0, 3).map((suggestion, idx) => (
                                    <Button
                                        key={idx}
                                        variant="outline"
                                        size="sm"
                                        className="text-xs h-auto py-1.5"
                                        onClick={() => handleAiChat(suggestion)}
                                    >
                                        {suggestion.length > 40 ? suggestion.substring(0, 40) + '...' : suggestion}
                                    </Button>
                                ))}
                            </div>
                        )}

                        {/* Chat Input */}
                        <div className="flex gap-2">
                            <Input
                                placeholder="Ask anything about your schema..."
                                value={aiChatInput}
                                onChange={(e) => setAiChatInput(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        handleAiChat();
                                    }
                                }}
                                disabled={aiChatLoading}
                            />
                            <Button
                                onClick={() => handleAiChat()}
                                disabled={aiChatLoading || !aiChatInput.trim()}
                            >
                                Send
                            </Button>
                        </div>
                    </div>
                    <SheetFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setAiChatMessages([]);
                                setAiChatSuggestions([]);
                            }}
                            disabled={aiChatMessages.length === 0}
                        >
                            Clear Chat
                        </Button>
                        <Button variant="outline" onClick={() => setAiChatOpen(false)}>
                            Close
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            <Dialog open={starterConfirmOpen} onOpenChange={setStarterConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Load starter</DialogTitle>
                        <DialogDescription>
                            Load starter &quot;{pendingStarter?.name}&quot;. Replace the current draft or merge with it?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setStarterConfirmOpen(false);
                                setPendingStarter(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button variant="outline" onClick={() => confirmStarter(false)}>
                            Merge
                        </Button>
                        <Button onClick={() => confirmStarter(true)}>Replace draft</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={importConfirmOpen} onOpenChange={setImportConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Import from codebase</DialogTitle>
                        <DialogDescription>
                            Use the imported draft to replace the current draft or merge with it?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setImportConfirmOpen(false);
                                setImportedYaml(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button variant="outline" onClick={() => confirmImport(false)}>
                            Merge
                        </Button>
                        <Button onClick={() => confirmImport(true)}>Replace draft</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={wizardOpen !== null} onOpenChange={(open) => !open && setWizardOpen(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {wizardOpen === 'addModel' && 'Add model'}
                            {wizardOpen === 'addCrud' && 'Add CRUD resource'}
                            {wizardOpen === 'addRelationship' && 'Add relationship'}
                            {wizardOpen === 'addPage' && 'Add page'}
                        </DialogTitle>
                        <DialogDescription>
                            {wizardOpen === 'addModel' && 'Add a new model to the draft. Optionally infer columns from the database.'}
                            {wizardOpen === 'addCrud' && 'Add full CRUD (actions, pages, routes) for an existing model.'}
                            {wizardOpen === 'addRelationship' && 'Add a relationship between two models.'}
                            {wizardOpen === 'addPage' && 'Add a page to the draft.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-4 py-2">
                        {wizardOpen === 'addModel' && (
                            <>
                                <div className="space-y-2">
                                    <Label>Model name</Label>
                                    <Input
                                        value={(wizardForm.name as string) ?? ''}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, name: e.target.value }))}
                                        placeholder="e.g. Post"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Table (optional)</Label>
                                    <Input
                                        value={(wizardForm.table as string) ?? ''}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, table: e.target.value }))}
                                        placeholder="e.g. posts"
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="wizard-infer"
                                        checked={!!wizardForm.infer_from_db}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, infer_from_db: e.target.checked }))}
                                    />
                                    <Label htmlFor="wizard-infer">Infer columns from database</Label>
                                </div>
                            </>
                        )}
                        {wizardOpen === 'addCrud' && (
                            <div className="space-y-2">
                                <Label>Model name</Label>
                                <Input
                                    value={(wizardForm.model_name as string) ?? ''}
                                    onChange={(e) => setWizardForm((f) => ({ ...f, model_name: e.target.value }))}
                                    placeholder="e.g. Post"
                                />
                            </div>
                        )}
                        {wizardOpen === 'addRelationship' && (
                            <>
                                <div className="space-y-2">
                                    <Label>From model</Label>
                                    <Input
                                        value={(wizardForm.from_model as string) ?? ''}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, from_model: e.target.value }))}
                                        placeholder="e.g. Comment"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Type</Label>
                                    <select
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={(wizardForm.type as string) ?? 'belongsTo'}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, type: e.target.value }))}
                                    >
                                        <option value="belongsTo">belongsTo</option>
                                        <option value="hasMany">hasMany</option>
                                        <option value="hasOne">hasOne</option>
                                        <option value="belongsToMany">belongsToMany</option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>To model</Label>
                                    <Input
                                        value={(wizardForm.to_model as string) ?? ''}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, to_model: e.target.value }))}
                                        placeholder="e.g. Post"
                                    />
                                </div>
                            </>
                        )}
                        {wizardOpen === 'addPage' && (
                            <>
                                <div className="space-y-2">
                                    <Label>Page name</Label>
                                    <Input
                                        value={(wizardForm.name as string) ?? ''}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, name: e.target.value }))}
                                        placeholder="e.g. Dashboard"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Type</Label>
                                    <select
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={(wizardForm.type as string) ?? 'index'}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, type: e.target.value }))}
                                    >
                                        <option value="index">index</option>
                                        <option value="show">show</option>
                                        <option value="create">create</option>
                                        <option value="edit">edit</option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Model (optional)</Label>
                                    <Input
                                        value={(wizardForm.model as string) ?? ''}
                                        onChange={(e) => setWizardForm((f) => ({ ...f, model: e.target.value }))}
                                        placeholder="e.g. Post"
                                    />
                                </div>
                            </>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setWizardOpen(null)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={wizardLoading}
                            onClick={async () => {
                                if (!wizardOpen) return;
                                const endpoint =
                                    wizardOpen === 'addModel'
                                        ? '/architect/api/wizard/add-model'
                                        : wizardOpen === 'addCrud'
                                          ? '/architect/api/wizard/add-crud-resource'
                                          : wizardOpen === 'addRelationship'
                                            ? '/architect/api/wizard/add-relationship'
                                            : '/architect/api/wizard/add-page';
                                const body: Record<string, unknown> = { ...wizardForm, yaml: draftYaml };
                                setWizardLoading(true);
                                const { ok, data } = await apiFetch(endpoint, {
                                    method: 'POST',
                                    body: JSON.stringify(body),
                                });
                                setWizardLoading(false);
                                const result = data as { yaml?: string; error?: string };
                                if (ok && result.yaml) {
                                    setDraftYaml(result.yaml);
                                    setWizardOpen(null);
                                }
                            }}
                        >
                            {wizardLoading ? 'Applying…' : 'Apply'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <CommandDialog open={paletteOpen} onOpenChange={setPaletteOpen}>
                <CommandInput placeholder="Search actions..." />
                <CommandList>
                    <CommandEmpty>No results.</CommandEmpty>
                    <CommandGroup heading="Draft">
                        <StudioCommandItem
                            onSelect={() => {
                                handleSave();
                                setPaletteOpen(false);
                            }}
                            disabled={saveStatus === 'saving' || !draftYaml.trim()}
                        >
                            Save draft
                        </StudioCommandItem>
                        <StudioCommandItem
                            onSelect={() => {
                                handleValidate();
                                setPaletteOpen(false);
                            }}
                            disabled={!draftYaml.trim()}
                        >
                            Check Schema <span className="ml-auto text-muted-foreground">V</span>
                        </StudioCommandItem>
                        <StudioCommandItem
                            onSelect={() => {
                                handlePlan();
                                setPaletteOpen(false);
                            }}
                            disabled={!draftYaml.trim()}
                        >
                            Preview Changes <span className="ml-auto text-muted-foreground">P</span>
                        </StudioCommandItem>
                        <StudioCommandItem
                            onSelect={() => {
                                handleBuild();
                                setPaletteOpen(false);
                            }}
                            disabled={!draftYaml.trim()}
                        >
                            Generate Code <span className="ml-auto text-muted-foreground">B</span>
                        </StudioCommandItem>
                    </CommandGroup>
                    <CommandGroup heading="Templates">
                        {starters.length > 0 &&
                            starters.map((name) => (
                                <StudioCommandItem
                                    key={name}
                                    onSelect={() => {
                                        loadStarter(name);
                                        setPaletteOpen(false);
                                    }}
                                >
                                    Start from template: {name}
                                </StudioCommandItem>
                            ))}
                        <StudioCommandItem
                            onSelect={() => {
                                runImport();
                                setPaletteOpen(false);
                            }}
                            disabled={importLoading}
                        >
                            Import from codebase
                        </StudioCommandItem>
                    </CommandGroup>
                    <CommandGroup heading="View">
                        <StudioCommandItem
                            onSelect={() => {
                                fitViewRef.current?.();
                                setPaletteOpen(false);
                            }}
                        >
                            Fit view / Center diagram <span className="ml-auto text-muted-foreground">F</span>
                        </StudioCommandItem>
                        <StudioCommandItem
                            onSelect={() => {
                                setShowMinimap((v) => !v);
                                setPaletteOpen(false);
                            }}
                        >
                            {showMinimap ? 'Hide minimap' : 'Show minimap'}
                        </StudioCommandItem>
                        <StudioCommandItem
                            onSelect={() => {
                                setModelListPanelOpen((v) => !v);
                                setPaletteOpen(false);
                            }}
                        >
                            {modelListPanelOpen ? 'Hide model list' : 'Show model list'}
                        </StudioCommandItem>
                        <StudioCommandItem
                            onSelect={() => {
                                setShowYamlSplit((v) => !v);
                                setPaletteOpen(false);
                            }}
                        >
                            {showYamlSplit ? 'Diagram only' : 'YAML split'}
                        </StudioCommandItem>
                        {draftHistory.length > 0 &&
                            draftHistory.map((y, i) => (
                                <StudioCommandItem
                                    key={i}
                                    onSelect={() => {
                                        setDraftYaml(y);
                                        setPaletteOpen(false);
                                    }}
                                >
                                    History: Restore #{i + 1}
                                </StudioCommandItem>
                            ))}
                    </CommandGroup>
                    {ai_enabled && (
                        <CommandGroup heading="AI">
                            <StudioCommandItem
                                onSelect={() => {
                                    setAiError(null);
                                    setProposedYaml(null);
                                    setAiPanelOpen(true);
                                    setPaletteOpen(false);
                                }}
                            >
                                Describe with AI
                            </StudioCommandItem>
                            <StudioCommandItem
                                onSelect={() => {
                                    setAiChatOpen(true);
                                    setPaletteOpen(false);
                                }}
                            >
                                AI Assistant <span className="ml-auto text-muted-foreground">A</span>
                            </StudioCommandItem>
                        </CommandGroup>
                    )}
                </CommandList>
                <div className="border-t border-sidebar-border px-3 py-2 text-xs text-muted-foreground">
                    ⌘K search · F Fit view · V Validate · P Plan · B Build
                </div>
            </CommandDialog>

            {/* Features Panel */}
            <Sheet open={featuresPanelOpen} onOpenChange={setFeaturesPanelOpen}>
                <SheetContent side="right" className="w-[400px] sm:w-[540px]">
                    <SheetHeader>
                        <SheetTitle>Available Schema Features</SheetTitle>
                        <SheetDescription>
                            These features can be added to your models based on installed packages.
                            Green features are available, gray features require additional packages.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="mt-4 space-y-3 overflow-y-auto pr-2" style={{ maxHeight: 'calc(100vh - 200px)' }}>
                        {schema_hints &&
                            Object.entries(schema_hints).map(([key, hint]) => (
                                <div
                                    key={key}
                                    className={cn(
                                        'rounded-lg border p-3',
                                        hint.available
                                            ? 'border-green-500/30 bg-green-500/5'
                                            : 'border-muted bg-muted/30 opacity-60'
                                    )}
                                >
                                    <div className="flex items-center justify-between">
                                        <code className="font-mono text-sm font-medium">
                                            {hint.schema_key}: true
                                        </code>
                                        <Badge variant={hint.available ? 'default' : 'secondary'}>
                                            {hint.available ? 'Available' : 'Not installed'}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {hint.description}
                                    </p>
                                    <div className="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                                        <span>Requires:</span>
                                        <code className="rounded bg-muted px-1 py-0.5 font-mono">
                                            {hint.requires_package}
                                        </code>
                                    </div>
                                </div>
                            ))}
                    </div>
                    {variants && (
                        <div className="mt-6 border-t pt-4">
                            <h4 className="mb-3 font-medium">Generator Variants</h4>
                            <div className="grid grid-cols-2 gap-2 text-sm">
                                <div className="flex justify-between rounded bg-muted/50 px-2 py-1">
                                    <span className="text-muted-foreground">Stack</span>
                                    <span className="font-mono">{variants.stack}</span>
                                </div>
                                <div className="flex justify-between rounded bg-muted/50 px-2 py-1">
                                    <span className="text-muted-foreground">API Auth</span>
                                    <span className="font-mono">{variants.api_auth}</span>
                                </div>
                                <div className="flex justify-between rounded bg-muted/50 px-2 py-1">
                                    <span className="text-muted-foreground">Tests</span>
                                    <span className="font-mono">{variants.test_framework}</span>
                                </div>
                                <div className="flex justify-between rounded bg-muted/50 px-2 py-1">
                                    <span className="text-muted-foreground">Admin</span>
                                    <span className="font-mono">{variants.admin_panel}</span>
                                </div>
                                <div className="flex justify-between rounded bg-muted/50 px-2 py-1">
                                    <span className="text-muted-foreground">API Docs</span>
                                    <span className="font-mono">{variants.api_docs}</span>
                                </div>
                                <div className="flex justify-between rounded bg-muted/50 px-2 py-1">
                                    <span className="text-muted-foreground">Broadcasting</span>
                                    <span className="font-mono">{variants.broadcasting}</span>
                                </div>
                            </div>
                        </div>
                    )}
                    <SheetFooter className="mt-6">
                        <Button variant="outline" onClick={() => setFeaturesPanelOpen(false)}>
                            Close
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </TooltipProvider>
    );
}
