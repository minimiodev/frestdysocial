const { execSync } = require('child_process');

// Session mode pooler: port 5432, host pooler (có IPv4!)
// Đây là cách duy nhất khi port 5432 direct bị chặn
const poolerSessionUrl = 'postgresql://postgres.rokmiiisjuowipexrxws:Hello%4012389vn@aws-0-ap-northeast-2.pooler.supabase.com:5432/postgres?sslmode=require';
const poolerTransactionUrl = 'postgresql://postgres.rokmiiisjuowipexrxws:Hello%4012389vn@aws-0-ap-northeast-2.pooler.supabase.com:6543/postgres?sslmode=require&pgbouncer=true';

const environments = ['production', 'preview', 'development'];

console.log('Switching to Supabase Connection Pooler (IPv4 compatible)...\n');

// Đổi DATABASE_URL sang session mode pooler
for (const env of environments) {
  try {
    execSync(`npx vercel env rm DATABASE_URL ${env} --yes`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    execSync(`npx vercel env add DATABASE_URL ${env}`, { input: poolerTransactionUrl, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`✅ DATABASE_URL [${env}] → Transaction Pooler port 6543`);
  } catch (e) {
    console.log(`❌ DATABASE_URL [${env}]: ${e.message.substring(0, 80)}`);
  }

  // Giữ DIRECT_URL là session pooler (cho migrations)
  try {
    execSync(`npx vercel env rm DIRECT_URL ${env} --yes`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    execSync(`npx vercel env add DIRECT_URL ${env}`, { input: poolerSessionUrl, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`✅ DIRECT_URL   [${env}] → Session Pooler port 5432`);
  } catch (e) {
    console.log(`❌ DIRECT_URL [${env}]: ${e.message.substring(0, 80)}`);
  }
}

console.log('\n✅ Done! Triggering redeploy...');
try {
  execSync('npx vercel --prod --yes', { encoding: 'utf8', stdio: 'inherit' });
} catch (e) {}
