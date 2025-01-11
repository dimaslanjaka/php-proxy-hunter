import { exec } from 'child_process';

// Function to check if the system is a VPS (Linux)
export function isVPS() {
  return new Promise((resolve, reject) => {
    exec('cat /proc/cpuinfo', (error, stdout, stderr) => {
      if (error || stderr) {
        reject(false);
        return;
      }

      // Check for virtualization keywords
      if (stdout.includes('hypervisor') || stdout.includes('KVM') || stdout.includes('VMware')) {
        resolve(true); // It's a VPS
      } else {
        resolve(false); // It's a local machine
      }
    });
  });
}
