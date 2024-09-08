function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthLevel = document.getElementById('password-strength-level');
    const requirements = {
        length: document.getElementById('length-requirement'),
        lowercase: document.getElementById('lowercase-requirement'),
        uppercase: document.getElementById('uppercase-requirement'),
        number: document.getElementById('number-requirement'),
        special: document.getElementById('special-requirement')
    };
    const submitButton = document.getElementById('password-submit-btn');
    let passedTests = 0;

    const checks = {
        length: password.length >= 8,
        lowercase: /[a-z]/.test(password),
        uppercase: /[A-Z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
    };

    for (const [key, value] of Object.entries(checks)) {
        const icon = requirements[key].querySelector('.icon');
        if (value) {
            requirements[key].classList.add('valid');
            icon.classList.remove('bi-x-circle-fill', 'text-danger');
            icon.classList.add('bi-check-circle-fill', 'text-success');
            passedTests++;
        } else {
            requirements[key].classList.remove('valid');
            icon.classList.remove('bi-check-circle-fill', 'text-success');
            icon.classList.add('bi-x-circle-fill', 'text-danger');
        }
    }

    const strengthPercentage = (passedTests / Object.keys(checks).length) * 100;
    strengthLevel.style.width = `${strengthPercentage}%`;
    strengthLevel.style.backgroundColor = passedTests >= 4 ? 'green' : 'red';
    submitButton.disabled = passedTests < 4;
}
