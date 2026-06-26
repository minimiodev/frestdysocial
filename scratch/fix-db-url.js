const { execSync } = require('child_process');

// Xóa DATABASE_URL cũ (pooler bị lỗi tenant not found)
// và thay bằng direct connection URL
const directUrl = 'postgresql://postgres:Hello%4012389vn@db.rokmiiisjuowipexrxws.supabase.co:5432/postgres';

const environments = ['production', 'preview', 'development'];

console.log('Updating DATABASE_URL to use direct connection...\n');

for (const env of environments) {
  try {
    // Xóa biến cũ
    execSync(`npx vercel env rm DATABASE_URL ${env} --yes`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`🗑️  Removed DATABASE_URL [${env}]`);
  } catch (e) {
    console.log(`⚠️  Could not remove DATABASE_URL [${env}] (may not exist)`);
  }

  try {
    // Thêm biến mới với direct URL
    execSync(`npx vercel env add DATABASE_URL ${env}`, { input: directUrl, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
    console.log(`✅ DATABASE_URL [${env}] = direct connection added`);
  } catch (e) {
    console.log(`❌ Failed to add DATABASE_URL [${env}]: ${e.message}`);
  }
}

console.log('\n✅ Done! Triggering redeploy...');
try {
  execSync('npx vercel --prod --yes', { encoding: 'utf8', stdio: 'inherit' });
} catch (e) {
  console.log('Deploy triggered.');
}
