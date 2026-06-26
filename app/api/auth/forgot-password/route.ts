import { NextRequest, NextResponse } from "next/server";
import { db } from "@/lib/db";
import crypto from "crypto";
import nodemailer from "nodemailer";

export const dynamic = "force-dynamic";

export async function POST(req: NextRequest) {
  try {
    const { email } = await req.json();

    if (!email || !email.trim()) {
      return NextResponse.json({ error: "Vui lòng nhập địa chỉ email." }, { status: 400 });
    }

    // 1. Tìm người dùng theo email
    const user = await db.user.findUnique({
      where: { email: email.trim().toLowerCase() },
    });

    if (!user) {
      // Vì lý do bảo mật, ta không thông báo rõ email có tồn tại hay không,
      // Nhưng để Dũng dễ debug, ta sẽ trả về thông báo chung.
      return NextResponse.json({ message: "Nếu email tồn tại trong hệ thống, bạn sẽ nhận được liên kết khôi phục." }, { status: 200 });
    }

    // 2. Tạo mã token đặt lại mật khẩu ngẫu nhiên
    const resetToken = crypto.randomBytes(32).toString("hex");
    const resetTokenExpires = new Date(Date.now() + 3600000); // Hết hạn sau 1 giờ

    // 3. Lưu vào database
    await db.user.update({
      where: { id: user.id },
      data: {
        resetToken: resetToken,
        resetTokenExpires: resetTokenExpires,
      },
    });

    // 4. Cấu hình transporter gửi mail bằng SMTP Gmail của Dũng
    const transporter = nodemailer.createTransport({
      host: process.env.SMTP_HOST || "smtp.gmail.com",
      port: parseInt(process.env.SMTP_PORT || "465"),
      secure: true, // true cho port 465
      auth: {
        user: process.env.SMTP_USER || "dungflows@gmail.com",
        pass: process.env.SMTP_PASS || "",
      },
    });

    // Lấy origin URL của site
    const origin = req.nextUrl.origin;
    const resetUrl = `${origin}/reset-password?token=${resetToken}`;

    // 5. Nội dung email gửi cho người dùng
    const mailOptions = {
      from: `"${process.env.SMTP_USER || "Frest Social"}" <${process.env.SMTP_USER || "dungflows@gmail.com"}>`,
      to: user.email,
      subject: "[Frest] Yêu cầu đặt lại mật khẩu tài khoản của bạn",
      html: `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 12px; background-color: #ffffff;">
          <h2 style="color: #1877f2; text-align: center;">Khôi phục mật khẩu Frest</h2>
          <p>Xin chào <strong>${user.fullName || user.username}</strong>,</p>
          <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản Frest của bạn. Vui lòng bấm vào nút bên dưới để tiến hành đặt mật khẩu mới (liên kết này có hiệu lực trong vòng 1 giờ):</p>
          <div style="text-align: center; margin: 30px 0;">
            <a href="${resetUrl}" style="background-color: #1877f2; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">Đặt lại mật khẩu</a>
          </div>
          <p style="color: #666666; font-size: 12px;">Nếu nút trên không hoạt động, bạn có thể copy và dán liên kết sau vào trình duyệt:</p>
          <p style="color: #1877f2; font-size: 12px; word-break: break-all;">${resetUrl}</p>
          <hr style="border: none; border-top: 1px solid #eeeeee; margin: 20px 0;" />
          <p style="color: #999999; font-size: 11px; text-align: center;">Nếu bạn không yêu cầu việc này, vui lòng bỏ qua email này. Mật khẩu của bạn sẽ được giữ nguyên an toàn.</p>
        </div>
      `,
    };

    // 6. Gửi email
    await transporter.sendMail(mailOptions);

    return NextResponse.json({
      message: "Nếu email tồn tại trong hệ thống, bạn sẽ nhận được liên kết khôi phục.",
    }, { status: 200 });

  } catch (error: any) {
    console.error("Forgot Password SMTP Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi gửi email khôi phục." }, { status: 500 });
  }
}
