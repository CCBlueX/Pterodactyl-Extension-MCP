import { afterEach, expect, test, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { createExtensionTestHost, type ExtensionTestHost } from '@pterodactyl/sdk/testing';
import * as api from '../api';
import McpScreen from './McpScreen';

vi.mock('../api', async (importOriginal) => ({ ...(await importOriginal<typeof import('../api')>()), getTools: vi.fn() }));

let host: ExtensionTestHost | undefined;

afterEach(() => {
    cleanup();
    host?.dispose();
    host = undefined;
});

const tool = (name: string, group: string, kind: api.ToolKind): api.ToolInfo => ({ name, title: name, description: `${name} does things.`, group, kind });

test('shows the endpoint and the tools grouped with their kind', async () => {
    vi.mocked(api.getTools).mockResolvedValue({
        enabled: true,
        version: '1.1.0',
        tools: [tool('read_file', 'Files', 'read'), tool('delete_files', 'Files', 'destroy'), tool('send_command', 'Console', 'control')],
    });
    host = createExtensionTestHost({ extensionId: 'mcp', prefix: 'mcp' });
    render(<McpScreen />, { wrapper: host.Wrapper });

    expect((await screen.findAllByText(/\/api\/client\/extensions\/mcp/)).length).toBeGreaterThan(0);
    expect(await screen.findByText('delete_files')).toBeTruthy();
    expect(screen.getByText('Files')).toBeTruthy();
    expect(screen.getByText('Console')).toBeTruthy();
    expect(screen.getByText('Destructive')).toBeTruthy();
});

test('says when an administrator turned MCP off', async () => {
    vi.mocked(api.getTools).mockResolvedValue({ enabled: false, version: '1.1.0', tools: [] });
    host = createExtensionTestHost({ extensionId: 'mcp', prefix: 'mcp' });
    render(<McpScreen />, { wrapper: host.Wrapper });

    expect(await screen.findByText('An administrator turned MCP off.')).toBeTruthy();
});
