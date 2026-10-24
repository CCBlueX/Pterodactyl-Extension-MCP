import { useQuery } from '@tanstack/react-query';
import { Badge, CopyOnClick, PageContentBlock, PanelLink, Spinner, TitledGreyBox, httpErrorToHuman } from '@pterodactyl/sdk';
import { getTools, keys, type ToolInfo } from '../api';

function Code({ children }: { children: string }) {
    return (
        <CopyOnClick text={children}>
            <pre className='mcp:rounded-sm mcp:bg-terminal mcp:px-4 mcp:py-3 mcp:font-mono mcp:text-sm mcp:break-all mcp:whitespace-pre-wrap mcp:text-terminal-foreground'>{children}</pre>
        </CopyOnClick>
    );
}

function KindBadge({ tool }: { tool: ToolInfo }) {
    if (tool.kind === 'destroy') return <Badge tone='danger' size='sm'>Destructive</Badge>;
    if (tool.kind === 'write') return <Badge tone='warning' size='sm'>Changes</Badge>;
    if (tool.kind === 'control') return <Badge tone='info' size='sm'>Control</Badge>;
    return null;
}

function groupTools(tools: ToolInfo[]): [string, ToolInfo[]][] {
    const groups = new Map<string, ToolInfo[]>();
    for (const tool of tools) groups.set(tool.group, [...(groups.get(tool.group) ?? []), tool]);

    return [...groups.entries()];
}

function Tools() {
    const catalog = useQuery({ queryKey: keys.tools, queryFn: getTools });

    if (catalog.isLoading) return <Spinner centered />;
    if (catalog.isError) return <p className='mcp:text-sm mcp:text-destructive'>{httpErrorToHuman(catalog.error)}</p>;
    if (!catalog.data!.enabled) return <p className='mcp:text-sm mcp:text-muted-foreground'>An administrator turned MCP off.</p>;

    return (
        <div className='mcp:grid mcp:gap-5'>
            {groupTools(catalog.data!.tools).map(([group, tools]) => (
                <section key={group}>
                    <h3 className='mcp:mb-2 mcp:text-xs mcp:uppercase mcp:text-muted-foreground'>{group}</h3>
                    <ul className='mcp:grid mcp:gap-2 mcp:sm:grid-cols-2'>
                        {tools.map((tool) => (
                            <li key={tool.name} className='mcp:rounded-sm mcp:border mcp:border-border mcp:p-3'>
                                <p className='mcp:flex mcp:items-center mcp:gap-2 mcp:font-mono mcp:text-sm mcp:text-foreground'>
                                    {tool.name}
                                    <KindBadge tool={tool} />
                                </p>
                                <p className='mcp:mt-1 mcp:text-xs mcp:text-muted-foreground'>{tool.description}</p>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}

export default function McpScreen() {
    const url = `${window.location.origin}/api/client/extensions/mcp`;
    const json = JSON.stringify({ mcpServers: { pterodactyl: { type: 'http', url, headers: { Authorization: 'Bearer ptlc_YOUR_KEY' }, timeout: 120000 } } }, null, 2);

    return (
        <PageContentBlock title='MCP'>
            <div className='mcp:grid mcp:gap-6'>
                <TitledGreyBox title='Connect an AI assistant'>
                    <ol className='mcp:grid mcp:list-decimal mcp:gap-3 mcp:pl-5 mcp:text-sm'>
                        <li>
                            Create an API key under{' '}
                            <PanelLink destination={{ to: '/account/api' }} className='mcp:text-primary mcp:underline'>
                                API Credentials
                            </PanelLink>
                            . Limit it to your computer's IP address if you can.
                        </li>
                        <li>Add the server below to your assistant and replace the key.</li>
                        <li>Ask it something like "Restart my survival server and show me the console".</li>
                    </ol>
                    <p className='mcp:mt-4 mcp:mb-1 mcp:text-xs mcp:uppercase mcp:text-muted-foreground'>Endpoint</p>
                    <Code>{url}</Code>
                </TitledGreyBox>
                <TitledGreyBox title='Claude Code'>
                    <Code>{`claude mcp add --transport http pterodactyl ${url} --header "Authorization: Bearer ptlc_YOUR_KEY"`}</Code>
                </TitledGreyBox>
                <TitledGreyBox title='Cursor, VS Code, Claude Desktop and others'>
                    <p className='mcp:mb-2 mcp:text-sm mcp:text-muted-foreground'>
                        Add this to the MCP settings of your app. Waiting for a restart or a backup can take up to a minute, so allow long requests. Apps that only connect with an OAuth login are not supported.
                    </p>
                    <Code>{json}</Code>
                </TitledGreyBox>
                <TitledGreyBox title='Tools'>
                    <p className='mcp:mb-4 mcp:text-sm mcp:text-muted-foreground'>The assistant acts as you, so it can only use what your server permissions allow.</p>
                    <Tools />
                </TitledGreyBox>
            </div>
        </PageContentBlock>
    );
}
