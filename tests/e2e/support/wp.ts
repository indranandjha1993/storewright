import { execFileSync } from 'node:child_process';

export function wp(...args: string[]) {
	execFileSync('docker', ['compose', '--progress', 'quiet', 'run', '--rm', 'cli', 'wp', ...args], { stdio: 'pipe' });
}
