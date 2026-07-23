import { spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, statSync } from 'node:fs';
import { basename, dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const committedRoot = resolve(frontendRoot, 'projects/api-client/src/lib/generated');
const temporaryPrefix = '.salud-nexus-openapi-';
const temporaryParent = mkdtempSync(resolve(frontendRoot, temporaryPrefix));
const temporaryRoot = join(temporaryParent, 'generated');

try {
  if (!existsSync(committedRoot)) {
    throw new Error('Falta el cliente generado. Ejecuta `npm run api:generate`.');
  }

  const orvalBin = resolve(frontendRoot, 'node_modules/orval/dist/bin/orval.mjs');
  const generation = spawnSync(
    process.execPath,
    [orvalBin, '--config', 'orval.config.mjs', '--fail-on-warnings'],
    {
      cwd: frontendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        SALUD_NEXUS_API_OUTPUT: temporaryRoot,
      },
    },
  );

  if (generation.stdout) {
    process.stdout.write(generation.stdout);
  }
  if (generation.stderr) {
    process.stderr.write(generation.stderr);
  }
  if (generation.status !== 0) {
    process.exitCode = generation.status ?? 1;
  } else {
    const committed = snapshot(committedRoot);
    const regenerated = snapshot(temporaryRoot);
    const differences = compare(committed, regenerated);

    if (differences.length > 0) {
      process.stderr.write(
        `El cliente Angular generado no coincide con OpenAPI:\n${differences
          .map((path) => `- ${path}`)
          .join('\n')}\nEjecuta \`npm run api:generate\` y revisa el diff.\n`,
      );
      process.exitCode = 1;
    } else {
      process.stdout.write('Cliente Angular generado de forma reproducible y sin deriva.\n');
    }
  }
} finally {
  if (
    dirname(temporaryParent) !== frontendRoot ||
    !basename(temporaryParent).startsWith(temporaryPrefix)
  ) {
    throw new Error('Se rechazó limpiar un directorio temporal fuera del frontend.');
  }
  rmSync(temporaryParent, { force: true, recursive: true });
}

function snapshot(root) {
  const files = new Map();

  for (const path of walk(root)) {
    files.set(relative(root, path).replaceAll('\\', '/'), readFileSync(path, 'utf8'));
  }

  return files;
}

function walk(root) {
  const paths = [];

  for (const entry of readdirSync(root, { withFileTypes: true })) {
    const path = join(root, entry.name);
    if (entry.isDirectory()) {
      paths.push(...walk(path));
    } else if (entry.isFile() && statSync(path).isFile()) {
      paths.push(path);
    }
  }

  return paths.sort();
}

function compare(left, right) {
  const paths = new Set([...left.keys(), ...right.keys()]);

  return [...paths].filter((path) => left.get(path) !== right.get(path)).sort();
}
