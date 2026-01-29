import type { NodeProps } from 'reactflow';
import { Card, CardContent, CardHeader } from './ui/card';
import { Badge } from './ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from './ui/tooltip';

export interface ModelNodeData {
    label: string;
    tableName?: string;
    columns: string[];
    relationCount?: number;
}

export function ModelNode({ data }: NodeProps<ModelNodeData>) {
    const relationCount = data.relationCount ?? 0;
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Card className="min-w-[200px] border-sidebar-border shadow-md transition-shadow hover:shadow-lg studio-card">
                    <CardHeader className="flex flex-row items-center justify-between gap-2 pb-1 text-sm font-semibold">
                        <div className="flex min-w-0 flex-col">
                            <span>{data.label}</span>
                            {data.tableName && (
                                <span className="text-[10px] font-normal text-muted-foreground" title="Laravel table name">
                                    table: {data.tableName}
                                </span>
                            )}
                        </div>
                        {relationCount > 0 && (
                            <Badge variant="secondary" className="text-[10px] h-4">
                                {relationCount} rel
                            </Badge>
                        )}
                    </CardHeader>
                    <CardContent className="pt-0">
                        <ul className="space-y-0.5 text-[10px] text-muted-foreground">
                            {data.columns.length === 0 ? (
                                <li className="italic">no columns</li>
                            ) : (
                                data.columns.slice(0, 8).map((col) => (
                                    <li key={col} className="truncate font-mono">
                                        {col}
                                    </li>
                                ))
                            )}
                            {data.columns.length > 8 && (
                                <li className="italic">+{data.columns.length - 8} more</li>
                            )}
                        </ul>
                    </CardContent>
                </Card>
            </TooltipTrigger>
            <TooltipContent side="right" className="max-w-[200px]">
                <p className="font-semibold">{data.label} Model</p>
                <p className="text-[10px] mt-1">Laravel will generate a <code className="bg-muted px-1 rounded">app/Models/{data.label}.php</code> class and a migration for the <code className="bg-muted px-1 rounded">{data.tableName}</code> table.</p>
            </TooltipContent>
        </Tooltip>
    );
}
