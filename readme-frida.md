# Frida — quick reference for this repository

This README explains how to set up and use Frida with this repository. It includes missing steps commonly needed when working with Android/iOS devices: matching versions, transferring and running `frida-server`, common commands, and troubleshooting tips.

Note: a `frida-server` binary may be present in the repository root for convenience — always verify its version and architecture before using it on a device.

## Table of contents

- Prerequisites
- Match Frida versions
- Get the right frida-server binary
- Android: push and run frida-server
- iOS: push and run frida-server (jailbroken)
- Common Frida commands & examples
- Troubleshooting
- Notes about this repository

## Prerequisites

- Host (your PC): Python 3 and pip. On Windows use cmd.exe, PowerShell, or WSL as preferred.
- Install frida-tools on the host:

```bash
pip install --user frida-tools
# (optional) pin frida version to match the server: pip install --user frida==<version>
```

- Ensure Android `adb` is installed and working (for Android workflows).
- For iOS you'll need a jailbroken device with SSH access.

## Match Frida versions

Frida's Python bindings (frida-tools) and the `frida-server` binary running on the device must be the same major/minor version. If versions mismatch you will see errors like "Version mismatch".

To check the host frida version:

```bash
python -c "import frida; print(frida.__version__)"
frida-ps --version
```

Find matching frida-server builds on Frida's releases page: https://github.com/frida/frida/releases

## Get the right frida-server binary

1. Determine the device CPU/ABI (Android):

```bash
adb shell getprop ro.product.cpu.abi
adb shell getprop ro.product.cpu.abilist # may show multiple
```

2. Download the corresponding `frida-server-<version>-android-<arch>` binary from the Frida release.

3. Make sure the server binary and host-side frida are the same version (for example 16.0.22).

Note: This repo may contain a `frida-server` file in the project root — confirm its version and ABI before using it.

## Android: push and run frida-server

Typical flow (device must be rooted or you are using Magisk):

```bash
# push server to device
adb push frida-server /data/local/tmp/

# make executable
adb shell "su -c 'chmod 755 /data/local/tmp/frida-server'"

# run as root (background)
adb shell "su -c 'nohup /data/local/tmp/frida-server > /data/local/tmp/frida.log 2>&1 &'"

# verify server is listening by listing processes from host
frida-ps -Uai
```

## Quick

1st cmd

```bash
nox_adb.exe devices
nox_adb.exe connect IP:PORT
nox_adb.exe push .\frida-server /data/local/tmp/frida-server
nox_adb.exe shell "chmod 777 /data/local/tmp/frida-server"
adb shell "/data/local/tmp/frida-server &"
```

2nd cmd

find package name by `frida-ps -Uai`

```bash
frida --codeshare akabe1/frida-multiple-unpinning -f com.package.name -U
```


If you can't get root, you can use frida's spawning/attach flow against debuggable apps with `run-as` or by using a signed debug build. Most modern non-rooted devices require additional steps (e.g. patched binaries, root, or emulator).

To forward networked frida-server (if you started frida-server on the device and it listens on TCP):

```bash
# forward device port to host
adb forward tcp:27042 tcp:27042
# then point frida to the host port
frida-ps -H 127.0.0.1:27042 -a
```

## iOS: push and run frida-server (jailbroken devices)

1. Copy the matching `frida-server` to the device using scp/ssh, or `scp` through USB (if using usbmuxd).

```bash
scp frida-server root@<device-ip>:/usr/sbin/
ssh root@<device-ip> 'chmod +x /usr/sbin/frida-server && /usr/sbin/frida-server &'
```

2. Verify from host:

```bash
frida-ps -Uai
# or if using remote server
frida-ps -H <device-ip>:27042 -a
```

## Common Frida commands & examples

- List processes on a USB-connected device:

```bash
frida-ps -Uai
```

- Attach to a running process by name:

```bash
frida -U -n com.example.app -l scripts/hook.js
```

- Spawn an app, load a script and resume (useful for early hooks):

```bash
frida -U -f com.example.app -l scripts/hook.js --no-pause
```

- frida-trace example (trace calls matching a pattern):

```bash
frida-trace -U -i "open*" com.example.app
```

- Example minimal hook script (`scripts/hook.js`):

```js
// scripts/hook.js
Java.perform(function () {
  var Activity = Java.use('android.app.Activity');
  Activity.onResume.overload().implementation = function () {
    console.log('Activity.onResume called for ' + this);
    this.onResume();
  };
});
```

## Troubleshooting

- "Version mismatch" or "frida: error: attach: failed to spawn process": make sure host frida and frida-server match exactly. Reinstall host frida with `pip install --user frida==<version>`.
- "permission denied" when starting `frida-server`: ensure binary is executable (chmod 755) and run as root (su or adb root). Use Magisk on modern devices.
- Device not visible: run `adb devices` and ensure USB debugging is enabled.
- SELinux issues: temporarily set SELinux permissive during testing (not recommended on production devices) or use an emulator.
- If `frida-ps -U` lists no processes but `adb devices` shows the device, try restarting adb server: `adb kill-server && adb start-server`.

## Notes about this repository

- A `frida-server` binary may exist at the repository root — check its name and run `./frida-server --version` on the device to verify.
- Keep scripts in `scripts/` or a dedicated folder and reference them with `-l` when starting frida.

## Quick checklist

- [ ] Confirm host frida version: `python -c "import frida; print(frida.__version__)"`
- [ ] Download matching frida-server for device ABI
- [ ] Push binary, chmod +x, and run as root
- [ ] Run `frida-ps -Uai` and then attach or spawn as needed

## References

- Frida project: https://frida.re
- Frida GitHub releases (binaries): https://github.com/frida/frida/releases

---

If you want, I can also add a tiny example `scripts/hook.js` and a README section showing how to run it step-by-step using the `frida-server` binary included in this repo.
