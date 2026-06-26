const { execSync } = require('child_process');

// ĐÚNG HOST: aws-1 (không phải aws-0)
const databaseUrl = 'postgresql://postgres.rokmiiisjuowipexrxws:Hello%4012389vn@aws-1-ap-northeast-2.pooler.supabase.com:6543/postgres?pgbouncer=true';
const directUrl   = 'postgresql://postgres.rokmiiisjuowipexrxws:Hello%4012389vn@aws-1-ap-northeast-2.pooler.supabase.com:5432/postgres';

const environments = ['production', 'preview', 'development'];

console.log('Fixing pooler host: aws-0 → aws-1...\n');

for (const env of environments) {
  // Fix DATABASE_URL
  try {
    execSync(`npx vercel env rm DATABASE_URL ${env} --yes`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    execSync(`npx vercel env add DATABASE_URL ${env}`, { input: databaseUrl, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`✅ DATABASE_URL [${env}] → aws-1 port 6543`);
  } catch (e) { console.log(`❌ DATABASE_URL [${env}]`); }

  // Fix DIRECT_URL
  try {
    execSync(`npx vercel env rm DIRECT_URL ${env} --yes`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    execSync(`npx vercel env add DIRECT_URL ${env}`, { input: directUrl, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`✅ DIRECT_URL   [${env}] → aws-1 port 5432`);
  } catch (e) { console.log(`❌ DIRECT_URL [${env}]`); }
}

console.log('\n✅ Triggering redeploy...');
execSync('npx vercel --prod --yes', { encoding: 'utf8', stdio: 'inherit' });
