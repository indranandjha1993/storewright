import { execFileSync } from 'node:child_process';

export function wp(...args: string[]): string {
	return execFileSync('docker', ['compose', '--progress', 'quiet', 'run', '--rm', 'cli', 'wp', ...args], { stdio: 'pipe' }).toString().trim();
}
