import type { Edge, Node } from 'reactflow';
import { RELATION_LABELS } from '../constants';

/** Laravel-style: StudlyCase model name → snake_case table name (plural). Exported for Preview/tables list. */
export function modelToTableName(modelName: string): string {
    const snake = modelName
        .replace(/([A-Z])/g, '_$1')
        .toLowerCase()
        .replace(/^_/, '');
    if (snake.endsWith('s')) return `${snake}es`;
    if (snake.endsWith('y') && !/^[aeiou]y$/.test(snake.slice(-2))) return `${snake.slice(0, -1)}ies`;
    return `${snake}s`;
}

/** Laravel-style: relation target "User" or "User:author" → foreign key column name. */
export function belongsToFkColumn(targetStr: string): string {
    const t = targetStr.trim();
    const colon = t.indexOf(':');
    if (colon >= 0) {
        const alias = t.slice(colon + 1).trim();
        const snake = alias.replace(/([A-Z])/g, '_$1').toLowerCase().replace(/^_/, '');
        return `${snake}_id`;
    }
    const model = t.split(':')[0].trim();
    const snake = model.replace(/([A-Z])/g, '_$1').toLowerCase().replace(/^_/, '');
    return `${snake}_id`;
}

export interface DraftModels {
    [modelName: string]: {
        relationships?: {
            belongsTo?: string;
            hasMany?: string;
            hasOne?: string;
            belongsToMany?: string;
            morphTo?: string;
            morphMany?: string;
        };
        [key: string]: unknown;
    };
}

export interface ParsedDraft {
    models: DraftModels;
    schema_version?: string;
}

/**
 * Parse draft YAML/object into React Flow nodes and edges.
 * Nodes = one per model (id = model name). Edges = relationships.
 */
export function draftToFlow(draft: ParsedDraft): { nodes: Node[]; edges: Edge[] } {
    const models = draft.models ?? {};
    const nodeIds = Object.keys(models);
    const nodes: Node[] = nodeIds.map((id, i) => {
        const def = models[id];
        const columns = Object.keys(def).filter(
            (k) =>
                !['relationships', 'seeder', 'softDeletes', 'timestamps', 'traits'].includes(k) &&
                typeof def[k] === 'string',
        );
        const rels = def?.relationships;
        let relationCount = 0;
        if (rels && typeof rels === 'object') {
            for (const key of ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany', 'morphTo', 'morphMany']) {
                const v = rels[key as keyof typeof rels];
                if (v && typeof v === 'string') {
                    relationCount += v.split(',').length;
                }
            }
        }
        return {
            id,
            type: 'model',
            position: { x: 250 + (i % 3) * 320, y: 100 + Math.floor(i / 3) * 220 },
            data: {
                label: id,
                tableName: modelToTableName(id),
                columns: columns.map((c) => `${c}: ${String(def[c])}`),
                relationCount,
            },
        };
    });

    const edges: Edge[] = [];
    for (const source of nodeIds) {
        const rels = models[source].relationships;
        if (!rels || typeof rels !== 'object') continue;
        const relTypes = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany', 'morphTo', 'morphMany'] as const;
        for (const relType of relTypes) {
            const targetStr = rels[relType];
            if (!targetStr || typeof targetStr !== 'string') continue;
            const targetEntries = targetStr.split(',').map((t) => t.trim());
            for (const targetEntry of targetEntries) {
                const target = targetEntry.split(':')[0].trim();
                if (!target || !nodeIds.includes(target)) continue;
                const relLabel = RELATION_LABELS[relType] ?? relType;
                const label =
                    relType === 'belongsTo'
                        ? `${relLabel} (${belongsToFkColumn(targetEntry)})`
                        : relLabel;
                const edgeId = `${source}-${relType}-${target}-${targetEntry.replace(/:/g, '_')}`;
                edges.push({
                    id: edgeId,
                    source,
                    target,
                    label,
                    type: 'smoothstep',
                    labelStyle: { fontSize: 10 },
                    labelBgStyle: { fill: 'var(--color-card)', fillOpacity: 0.9 },
                    labelBgPadding: [4, 2] as [number, number],
                    labelBgBorderRadius: 4,
                });
            }
        }
    }

    return { nodes, edges };
}
