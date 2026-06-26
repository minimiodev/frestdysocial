const fs = require("fs");
const path = require("path");
const { S3Client, PutObjectCommand } = require("@aws-sdk/client-s3");

// Tự động đọc và phân tích file .env không cần thư viện bên thứ ba
try {
  const envPath = path.join(__dirname, "../.env");
  const envContent = fs.readFileSync(envPath, "utf-8");
  envContent.split("\n").forEach((line) => {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith("#")) return;
    const index = trimmed.indexOf("=");
    if (index !== -1) {
      const key = trimmed.substring(0, index).trim();
      let val = trimmed.substring(index + 1).trim();
      if (val.startsWith('"') && val.endsWith('"')) {
        val = val.slice(1, -1);
      }
      if (val.startsWith("'") && val.endsWith("'")) {
        val = val.slice(1, -1);
      }
      process.env[key] = val;
    }
  });
} catch (e) {
  console.error("Không thể đọc file .env:", e.message);
}

console.log("--- THÔNG TIN CẤU HÌNH R2 ĐANG TEST ---");
console.log("ENDPOINT:", process.env.R2_ENDPOINT);
console.log("ACCESS_KEY_ID:", process.env.R2_ACCESS_KEY_ID);
console.log("SECRET_ACCESS_KEY:", process.env.R2_SECRET_ACCESS_KEY ? "••••••••" + process.env.R2_SECRET_ACCESS_KEY.slice(-5) : "UNDEFINED");
console.log("BUCKET_NAME:", process.env.R2_BUCKET_NAME);
console.log("PUBLIC_URL:", process.env.NEXT_PUBLIC_R2_PUBLIC_URL);
console.log("--------------------------------------\n");

const s3Client = new S3Client({
  region: "auto",
  endpoint: process.env.R2_ENDPOINT || "",
  credentials: {
    accessKeyId: process.env.R2_ACCESS_KEY_ID || "",
    secretAccessKey: process.env.R2_SECRET_ACCESS_KEY || "",
  },
});

async function runTest() {
  try {
    console.log("Đang tiến hành gửi file test 'r2_test.txt' lên Cloudflare R2...");
    const buffer = Buffer.from("Hello Cloudflare R2 from Frest Social!");
    
    const command = new PutObjectCommand({
      Bucket: process.env.R2_BUCKET_NAME,
      Key: "scratch/r2_test.txt",
      Body: buffer,
      ContentType: "text/plain",
    });

    const result = await s3Client.send(command);
    console.log("\n✅ TẢI LÊN THÀNH CÔNG!");
    console.log("Thông tin kết quả từ Cloudflare:", result);
    
    const publicUrl = `${(process.env.NEXT_PUBLIC_R2_PUBLIC_URL || "").replace(/\/$/, "")}/scratch/r2_test.txt`;
    console.log("\n👉 Bạn có thể truy cập file test tại URL:");
    console.log(publicUrl);
    
  } catch (error) {
    console.log("\n❌ TẢI LÊN THẤT BẠI!");
    console.log("Chi tiết lỗi từ Cloudflare R2:");
    console.error(error);
  }
}

runTest();
