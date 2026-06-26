import { NextRequest, NextResponse } from "next/server";
import { getAuthenticatedUser, hashPassword, comparePassword } from "@/lib/auth";
import { db } from "@/lib/db";

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthenticatedUser(req);
    if (!user) {
      return NextResponse.json({ error: "Bạn cần đăng nhập." }, { status: 401 });
    }

    const body = await req.json();
    const { 
      action, // "profile", "password", "name_request", "age_verification"
      // Profile fields
      fullName, bio, livesAt, country, workplace, gender, isPrivate, showNsfw, avatarFilename, coverFilename,
      // Password fields
      currentPassword, newPassword,
      // Name request fields
      firstName, middleName, lastName, nameDisplayOrder,
      // Age verification fields
      idProofFilename, dob
    } = body;

    // 1. Cập nhật profile cơ bản
    if (action === "profile") {
      const updatedUser = await db.user.update({
        where: { id: user.id },
        data: {
          fullName: fullName !== undefined ? fullName.trim() : user.fullName,
          bio: bio !== undefined ? bio.trim() : user.bio,
          livesAt: livesAt !== undefined ? livesAt.trim() : user.livesAt,
          country: country !== undefined ? country.trim() : user.country,
          workplace: workplace !== undefined ? workplace.trim() : user.workplace,
          gender: gender !== undefined ? gender.trim() : user.gender,
          isPrivate: isPrivate !== undefined ? !!isPrivate : user.isPrivate,
          showNsfw: showNsfw !== undefined ? !!showNsfw : user.showNsfw,
          avatarFilename: avatarFilename !== undefined ? avatarFilename.trim() : user.avatarFilename,
          coverFilename: coverFilename !== undefined ? coverFilename.trim() : user.coverFilename,
        },
      });

      return NextResponse.json({ message: "Cập nhật hồ sơ thành công!", user: updatedUser });
    }

    // 2. Đổi mật khẩu
    if (action === "password") {
      if (!currentPassword || !newPassword) {
        return NextResponse.json({ error: "Vui lòng nhập mật khẩu hiện tại và mật khẩu mới." }, { status: 400 });
      }

      const isPasswordMatch = comparePassword(currentPassword, user.passwordHash);
      if (!isPasswordMatch) {
        return NextResponse.json({ error: "Mật khẩu hiện tại không chính xác." }, { status: 400 });
      }

      if (newPassword.length < 6) {
        return NextResponse.json({ error: "Mật khẩu mới phải từ 6 ký tự trở lên." }, { status: 400 });
      }

      const newHashed = hashPassword(newPassword);
      await db.user.update({
        where: { id: user.id },
        data: { passwordHash: newHashed },
      });

      return NextResponse.json({ message: "Đổi mật khẩu thành công!" });
    }

    // 3. Yêu cầu đổi tên (Name change request - Cần admin phê duyệt)
    if (action === "name_request") {
      if (!firstName || !lastName) {
        return NextResponse.json({ error: "Họ và tên không được để trống." }, { status: 400 });
      }

      // Đưa vào trạng thái chờ duyệt (pending)
      const updatedUser = await db.user.update({
        where: { id: user.id },
        data: {
          pendingFirstName: firstName.trim(),
          pendingMiddleName: middleName ? middleName.trim() : "",
          pendingLastName: lastName.trim(),
          pendingNameDisplayOrder: nameDisplayOrder || "last_middle_first",
          nameChangeStatus: "pending",
        },
      });

      return NextResponse.json({
        message: "Yêu cầu thay đổi tên hiển thị đã được gửi đến Admin để duyệt.",
        user: updatedUser,
      });
    }

    // 4. Xác thực độ tuổi (Age Verification - Cần tải ảnh CMND và admin duyệt)
    if (action === "age_verification") {
      if (!idProofFilename || !dob) {
        return NextResponse.json({ error: "Vui lòng cung cấp đầy đủ ngày sinh và ảnh chụp giấy tờ xác minh." }, { status: 400 });
      }

      const parsedDob = new Date(dob);
      if (isNaN(parsedDob.getTime())) {
        return NextResponse.json({ error: "Ngày sinh không hợp lệ." }, { status: 400 });
      }

      const updatedUser = await db.user.update({
        where: { id: user.id },
        data: {
          dob: parsedDob,
          idProofFilename: idProofFilename,
          ageVerificationStatus: "pending",
        },
      });

      return NextResponse.json({
        message: "Yêu cầu xác minh độ tuổi 18+ đã được gửi. Admin sẽ kiểm tra giấy tờ của bạn.",
        user: updatedUser,
      });
    }

    return NextResponse.json({ error: "Hành động không hợp lệ." }, { status: 400 });
  } catch (error) {
    console.error("Update Settings Error:", error);
    return NextResponse.json({ error: "Lỗi hệ thống" }, { status: 500 });
  }
}
