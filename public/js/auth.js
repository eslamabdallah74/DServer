document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const loginAlert = document.getElementById('loginAlert');

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;

      loginAlert.style.display = 'none';

      try {
        const response = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
          localStorage.setItem('admin_token', data.token);
          localStorage.setItem('admin_user', JSON.stringify(data.admin));
          window.location.href = '/dashboard.html';
        } else {
          loginAlert.textContent = data.message || 'فشل تسجيل الدخول.';
          loginAlert.style.display = 'block';
        }
      } catch (err) {
        loginAlert.textContent = 'حدث خطأ في الاتصال بالخادم.';
        loginAlert.style.display = 'block';
      }
    });
  }
});

function getAuthHeaders() {
  const token = localStorage.getItem('admin_token');
  return {
    'Content-Type': 'application/json',
    'Authorization': token ? `Bearer ${token}` : '',
  };
}

function checkAuthOrRedirect() {
  const token = localStorage.getItem('admin_token');
  if (!token) {
    window.location.href = '/login.html';
  }
}

function logout() {
  localStorage.removeItem('admin_token');
  localStorage.removeItem('admin_user');
  fetch('/api/auth/logout', { method: 'POST' }).finally(() => {
    window.location.href = '/login.html';
  });
}
