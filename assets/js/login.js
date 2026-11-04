// DermAve IMS - login page: Show / Hide password button

const password = document.getElementById('password');
const toggle = document.getElementById('togglePassword');

toggle.addEventListener('click', function () {
  if (password.type === 'password') {
    password.type = 'text';
    toggle.textContent = 'Hide';
  } else {
    password.type = 'password';
    toggle.textContent = 'Show';
  }
});