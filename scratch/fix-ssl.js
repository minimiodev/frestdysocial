const { execSync } = require('child_process');

// Thêm ?sslmode=require vào DATABASE_URL
const directUrlWithSSL = 'postgresql://postgres:Hello%4012389vn@db.rokmiiisjuowipexrxws.supabase.co:5432/postgres?sslmode=require';
const directUrl = 'postgresql://postgres:Hello%4012389vn@db.rokmiiisjuowipexrxws.supabase.co:5432/postgres';

const environments = ['production', 'preview', 'development'];

console.log('Updating DATABASE_URL with SSL support...\n');

for (const env of environments) {
  // Update DATABASE_URL with SSL
  try {
    execSync(`npx vercel env rm DATABASE_URL ${env} --yes`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    execSync(`npx vercel env add DATABASE_URL ${env}`, { input: directUrlWithSSL, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`✅ DATABASE_URL [${env}] updated with SSL`);
  } catch (e) {
    console.log(`❌ DATABASE_URL [${env}] failed: ${e.message.substring(0, 80)}`);
  }

  // Update DIRECT_URL with SSL too
  try {
    execSync(`npx vercel env rm DIRECT_URL ${env} --yes`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    execSync(`npx vercel env add DIRECT_URL ${env}`, { input: directUrlWithSSL, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`✅ DIRECT_URL [${env}] updated with SSL`);
  } catch (e) {
    console.log(`❌ DIRECT_URL [${env}] failed`);
  }
}

console.log('\n✅ Done! Triggering redeploy...');
try {
  execSync('npx vercel --prod --yes', { encoding: 'utf8', stdio: 'inherit' });
} catch (e) {
  console.log('Deploy triggered.');
}
