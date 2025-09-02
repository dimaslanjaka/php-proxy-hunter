import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

async function main() {
  try {
    const { stdout, stderr } = await execAsync('npx --yes update-browserslist-db@latest');
    if (stderr) {
      console.error(`Error output: ${stderr}`);
      return;
    }
    console.log(`Browserslist updated successfully: ${stdout}`);
  } catch (error) {
    console.error(`Error updating browserslist: ${error.message}`);
  }
}

main().catch(console.error);
