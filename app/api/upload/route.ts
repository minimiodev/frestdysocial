import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { uploadToR2 } from "@/lib/r2";

export const dynamic = "force-dynamic";

export async function POST(req: NextRequest) {
  try {
    // 1. Xác thực người dùng
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập để tải tệp lên." }, { status: 401 });
    }

    // 2. Đọc file từ Form Data
    const formData = await req.formData();
    const file = formData.get("file") as File | null;

    if (!file) {
      return NextResponse.json({ error: "Không tìm thấy tệp tin nào." }, { status: 400 });
    }

    // Giới hạn dung lượng tệp tin (50MB)
    const MAX_SIZE = 50 * 1024 * 1024; // 50MB
    if (file.size > MAX_SIZE) {
      return NextResponse.json({ error: "Tệp tin vượt quá giới hạn 50MB." }, { status: 400 });
    }

    // 3. Đọc dữ liệu Buffer của file
    const arrayBuffer = await file.arrayBuffer();
    const buffer = Buffer.from(arrayBuffer);

    // 4. Tạo khóa (key) file an toàn duy nhất trên R2
    const originalName = file.name || "media";
    const extension = originalName.split(".").pop() || "bin";
    const cleanName = originalName
      .replace(/[^a-zA-Z0-9]/g, "-")
      .substring(0, 30);
    const fileKey = `uploads/${user.id}-${Date.now()}-${cleanName}.${extension}`;

    // 5. Upload lên Cloudflare R2
    const fileUrl = await uploadToR2(buffer, fileKey, file.type);

    return NextResponse.json({
      message: "Tải tệp lên thành công!",
      url: fileUrl,
      key: fileKey,
      filename: file.name,
      contentType: file.type,
    }, { status: 200 });

  } catch (error: any) {
    // Log chi tiết lỗi để debug
    console.error("[Upload API] Error:", {
      message: error?.message,
      name: error?.name,
      code: error?.code,
      stack: error?.stack?.substring(0, 500),
    });

    // Xử lý các lỗi phổ biến
    if (error?.code === "NoSuchBucket") {
      return NextResponse.json({ error: "Bucket R2 không tồn tại. Vui lòng kiểm tra cấu hình R2_BUCKET_NAME." }, { status: 500 });
    }
    if (error?.code === "InvalidAccessKeyId" || error?.code === "SignatureDoesNotMatch") {
      return NextResponse.json({ error: "Thông tin xác thực R2 không hợp lệ. Vui lòng kiểm tra R2_ACCESS_KEY_ID và R2_SECRET_ACCESS_KEY." }, { status: 500 });
    }
    if (error?.message?.includes("fetch failed") || error?.message?.includes("ECONNREFUSED")) {
      return NextResponse.json({ error: "Không thể kết nối đến Cloudflare R2. Vui lòng kiểm tra R2_ENDPOINT." }, { status: 500 });
    }

    return NextResponse.json({ error: `Lỗi tải tệp tin lên Cloudflare R2: ${error?.message || "Unknown error"}` }, { status: 500 });
  }
}
