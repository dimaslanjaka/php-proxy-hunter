import path from 'path';
import { spawnSync } from 'child_process';
import { fileURLToPath, pathToFileURL } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Builds Tailwind CSS using the Tailwind CLI.
 *
 * - Reads from 'tailwind.input.css' in the current working directory.
 * - Outputs to 'src/react/components/theme.css'.
 * - Throws an error if the build fails.
 *
 * @throws {Error} If the Tailwind CSS build fails.
 */
export function buildTailwind() {
  const inputCss = path.join(process.cwd(), 'tailwind.input.css');
  const outputCss = path.join(process.cwd(), 'src/react/components/theme.css');
  const result = spawnSync('npx', ['--yes', '@tailwindcss/cli@latest', '-i', inputCss, '-o', outputCss], {
    stdio: 'inherit',
    shell: true
  });
  if (result.error || result.status !== 0) {
    throw result.error || new Error(`Tailwind CSS build failed with exit code ${result.status}`);
  }
  console.log('Tailwind CSS build completed successfully. Output CSS path:', outputCss);
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  buildTailwind();
}
