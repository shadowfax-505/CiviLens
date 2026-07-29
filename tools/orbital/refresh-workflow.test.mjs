import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test('weekly refresh is a fail-closed review-only candidate workflow', async () => {
  const workflow = await readFile('.github/workflows/orbital-imagery-candidate.yml', 'utf8');

  assert.match(workflow, /cron:\s*['"]15 3 \* \* 0['"]/);
  assert.match(workflow, /workflow_dispatch:/);
  assert.match(workflow, /ref:\s*\${{\s*github\.event\.repository\.default_branch\s*}}/);
  assert.match(workflow, /git fetch .*BASE_BRANCH/);
  assert.match(workflow, /git (?:switch|checkout).*origin\/\${BASE_BRANCH}/);
  assert.match(workflow, /contents:\s*write/);
  assert.match(workflow, /pull-requests:\s*write/);
  assert.match(workflow, /npm run test:orbital/);
  assert.match(workflow, /build:orbital-candidate/);
  assert.match(workflow, /public\/images\/orbital\/candidates/);
  assert.match(workflow, /gh pr (create|edit)/);
  assert.match(workflow, /Human visual review required/);
  assert.match(workflow, /git fetch .*refs\/heads\/\${branch}/);
  assert.match(workflow, /git show .*:\${candidate}/);
  assert.match(workflow, /git show .*:\${manifest}/);
  assert.match(workflow, /cmp --silent/);
  assert.match(workflow, /manifest\.source\.url/);
  assert.match(workflow, /manifest\.source\.projection/);
  assert.match(workflow, /manifest\.output\.sha256/);
  assert.match(workflow, /createHash\(['"]sha256['"]\)/);
  assert.doesNotMatch(workflow, /nasa-blue-marble-cloud-observation-composite-5400x2700\.jpg.*(?:>|mv|cp)/);
  assert.doesNotMatch(workflow, /manifest\.json.*(?:>|mv|cp)/);
  assert.doesNotMatch(workflow, /gh pr merge/);
});
