document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('login_success')) {
        showToast('Đăng nhập thành công! Chào mừng trở lại. 👋');
        localStorage.removeItem('login_success');
    }
    if (localStorage.getItem('register_success')) {
        showToast('Đăng ký tài khoản thành công! 🎉');
        localStorage.removeItem('register_success');
    }
    if (localStorage.getItem('post_created')) {
        showToast('Đã đăng Frest mới của bạn thành công! ✨');
        localStorage.removeItem('post_created');
    }
});
