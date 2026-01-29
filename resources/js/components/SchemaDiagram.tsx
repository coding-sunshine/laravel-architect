import {
    Background,
    BackgroundVariant,
    Controls,
    MiniMap,
    ReactFlow,
    ReactFlowProvider,
    useReactFlow,
    type Edge,
    type Node,
} from 'reactflow';
import { useEffect, useMemo } from 'react';
import 'reactflow/dist/style.css';
import type { ParsedDraft } from './draftToFlow';
import { draftToFlow } from './draftToFlow';
import { ModelNode } from './ModelNode';

const nodeTypes = { model: ModelNode };

export interface SchemaDiagramProps {
    draft: ParsedDraft | null;
    /** When set, fit view to center this node (model name = node id). Cleared after focus. */
    focusNodeId?: string | null;
    /** Called after focus animation so parent can clear focusNodeId. */
    onFocusDone?: () => void;
    /** When set, the matching edge is highlighted (stronger stroke). */
    highlightEdgeId?: string | null;
    /** When false, the minimap is hidden. */
    showMinimap?: boolean;
    /** Ref to expose fitView() to parent (e.g. for F shortcut). */
    fitViewRef?: React.MutableRefObject<(() => void) | null>;
}

function SchemaDiagramInner({ draft, focusNodeId, onFocusDone, highlightEdgeId, showMinimap = true, fitViewRef }: SchemaDiagramProps) {
    const { nodes, edges: rawEdges } = draft ? draftToFlow(draft) : { nodes: [] as Node[], edges: [] as Edge[] };
    const edges = useMemo(() => {
        if (!highlightEdgeId) return rawEdges;
        return rawEdges.map((e) =>
            e.id === highlightEdgeId ? { ...e, style: { ...e.style, stroke: 'var(--color-primary)', strokeWidth: 2 } } : e,
        );
    }, [rawEdges, highlightEdgeId]);
    const { fitView } = useReactFlow();

    useEffect(() => {
        if (fitViewRef) {
            fitViewRef.current = fitView;
            return () => {
                fitViewRef.current = null;
            };
        }
    }, [fitView, fitViewRef]);

    useEffect(() => {
        if (!focusNodeId || nodes.length === 0) return;
        const nodeIds = nodes.map((n) => n.id);
        if (!nodeIds.includes(focusNodeId)) return;
        fitView({ nodes: [{ id: focusNodeId }], duration: 300, padding: 0.2 });
        const t = setTimeout(() => onFocusDone?.(), 350);
        return () => clearTimeout(t);
    }, [focusNodeId, nodes, fitView, onFocusDone]);

    return (
        <div className="h-full w-full rounded-lg border border-sidebar-border bg-muted/20">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={nodeTypes}
                fitView
                minZoom={0.2}
                maxZoom={1.5}
                defaultEdgeOptions={{ type: 'smoothstep' }}
            >
                <Background variant={BackgroundVariant.Dots} gap={16} size={1} />
                <Controls className="!bottom-2 !left-2 !border-sidebar-border !bg-card" />
                {showMinimap && (
                    <MiniMap
                        className="!bottom-2 !right-2 !rounded-md !border !border-sidebar-border !bg-card"
                        nodeColor="var(--color-muted)"
                        maskColor="var(--color-background) / 0.8"
                    />
                )}
            </ReactFlow>
        </div>
    );
}

export function SchemaDiagram(props: SchemaDiagramProps) {
    return (
        <ReactFlowProvider>
            <SchemaDiagramInner {...props} />
        </ReactFlowProvider>
    );
}
