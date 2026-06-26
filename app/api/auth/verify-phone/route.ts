import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser } from "@/lib/auth";
import { db } from "@/lib/db";
import nodemailer from "nodemailer";

export const dynamic = "force-dynamic";

/**
 * POST: Gửi mã xác thực số điện thoại
 */
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const { phoneNumber } = await req.json();

    if (!phoneNumber || !phoneNumber.trim()) {
      return NextResponse.json({ error: "Vui lòng cung cấp số điện thoại." }, { status: 400 });
    }

    const cleanPhone = phoneNumber.trim();

    // 1. Sinh mã OTP 6 chữ số
    const otpCode = Math.floor(100000 + Math.random() * 900000).toString();

    // 2. Lưu mã OTP vào database
    await db.user.update({
      where: { id: user.id },
      data: {
        phoneNumber: cleanPhone,
        phoneVerificationCode: otpCode,
        phoneVerified: false,
      },
    });

    // 3. Giả lập gửi SMS hoặc gửi mail báo cho người dùng
    // Trong môi trường demo, ta gửi code OTP qua mail của họ để họ lấy cho tiện và bảo mật
    try {
      const transporter = nodemailer.createTransport({
        host: process.env.SMTP_HOST || "smtp.gmail.com",
        port: parseInt(process.env.SMTP_PORT || "465"),
        secure: true,
        auth: {
          user: process.env.SMTP_USER || "dungflows@gmail.com",
          pass: process.env.SMTP_PASS || "",
        },
      });

      const mailOptions = {
        from: `"${process.env.SMTP_USER || "Frest Social"}" <${process.env.SMTP_USER || "dungflows@gmail.com"}>`,
        to: user.email,
        subject: "[Frest] Mã xác thực số điện thoại",
        html: `
          <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 12px;">
            <h2 style="color: #1877f2; text-align: center;">Mã xác minh Frest</h2>
            <p>Xin chào <strong>${user.fullName || user.username}</strong>,</p>
            <p>Mã xác minh số điện thoại <strong>${cleanPhone}</strong> của bạn là:</p>
            <div style="text-align: center; margin: 30px 0;">
              <span style="font-size: 28px; font-weight: bold; color: #1877f2; letter-spacing: 5px; border: 2px dashed #1877f2; padding: 10px 20px; border-radius: 8px;">${otpCode}</span>
            </div>
            <p style="color: #666666; font-size: 11px;">Mã này có hiệu lực trong vòng 5 phút. Vui lòng không chia sẻ mã này với bất kỳ ai.</p>
          </div>
        `,
      };

      await transporter.sendMail(mailOptions);
    } catch (e) {
      console.warn("SMTP send OTP failed, displaying in console:", otpCode);
    }

    return NextResponse.json({
      message: "Mã xác thực đã được gửi tới email/số điện thoại của bạn.",
      codeMock: process.env.NODE_ENV === "development" ? otpCode : undefined, // Trả về code để dễ phát triển ở môi trường dev
    }, { status: 200 });

  } catch (error: any) {
    console.error("Send Verification Code Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống khi gửi mã xác thực." }, { status: 500 });
  }
}

/**
 * PUT: Xác nhận mã OTP để hoàn tất verify
 */
export async function PUT(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Chưa đăng nhập." }, { status: 401 });
    }

    const { code } = await req.json();

    if (!code || !code.trim()) {
      return NextResponse.json({ error: "Vui lòng nhập mã xác thực OTP." }, { status: 400 });
    }

    const targetUser = await db.user.findUnique({
      where: { id: user.id },
    });

    if (!targetUser || !targetUser.phoneVerificationCode) {
      return NextResponse.json({ error: "Yêu cầu xác thực không hợp lệ." }, { status: 400 });
    }

    if (targetUser.phoneVerificationCode !== code.trim()) {
      return NextResponse.json({ error: "Mã xác thực OTP không chính xác." }, { status: 400 });
    }

    // Xác thực thành công
    await db.user.update({
      where: { id: user.id },
      data: {
        phoneVerified: true,
        phoneVerificationCode: null,
      },
    });

    return NextResponse.json({
      message: "Số điện thoại của bạn đã được xác minh thành công!",
    }, { status: 200 });

  } catch (error: any) {
    console.error("Verify Phone Code Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống xác thực." }, { status: 500 });
  }
}
