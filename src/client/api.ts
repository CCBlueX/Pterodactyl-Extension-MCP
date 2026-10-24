import { http } from '@pterodactyl/sdk';

export type ToolKind = 'read' | 'control' | 'write' | 'destroy';

export interface ToolInfo {
    name: string;
    title: string;
    description: string;
    group: string;
    kind: ToolKind;
}

export interface ToolCatalog {
    enabled: boolean;
    version: string;
    tools: ToolInfo[];
}

export const keys = { tools: ['mcp', 'tools'] as const };

export const getTools = async () => (await http.get<ToolCatalog>('/api/client/extensions/mcp/tools')).data;
