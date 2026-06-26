function switchRegType(type) {
    document.getElementById('reg-type-input').value = type;
    const emailTabBtn = document.getElementById('tab-btn-email');
    const phoneTabBtn = document.getElementById('tab-btn-phone');
    const emailGroup = document.getElementById('email-group');
    const phoneGroup = document.getElementById('phone-group');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone_number');

    if (type === 'email') {
        // Toggle tab active classes
        emailTabBtn.classList.add('active');
        phoneTabBtn.classList.remove('active');
        
        // Toggle form groups
        emailGroup.style.display = 'block';
        phoneGroup.style.display = 'none';
        
        // Toggle required fields
        emailInput.required = true;
        phoneInput.required = false;
    } else {
        // Toggle tab active classes
        phoneTabBtn.classList.add('active');
        emailTabBtn.classList.remove('active');
        
        // Toggle form groups
        emailGroup.style.display = 'none';
        phoneGroup.style.display = 'block';
        
        // Toggle required fields
        emailInput.required = false;
        phoneInput.required = true;
    }
}
