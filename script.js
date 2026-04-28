function toggleForm(form) {
    if (form === 'signup') {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('signup-form').style.display = 'block';
    } else {
        document.getElementById('login-form').style.display = 'block';
        document.getElementById('signup-form').style.display = 'none';
    }
}
function showSection(sectionId) {
    document.querySelectorAll('.content-section').forEach(sec => sec.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
}

function signup() {
    const name = document.getElementById('signupName').value.trim();
    const email = document.getElementById('signupEmail').value.trim();
    const password = document.getElementById('signupPassword').value.trim();
    const role = document.getElementById('signupRole').value;

    const users = JSON.parse(localStorage.getItem('users')) || [];

    if (users.find(u => u.email === email)) {
        alert('Email already registered!');
        return;
    }

    users.push({ name, email, password, role });
    localStorage.setItem('users', JSON.stringify(users));

    alert('Account created successfully! Please log in.');
    toggleForm('login');
}

function login() {
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value.trim();

    const users = JSON.parse(localStorage.getItem('users')) || [];
    const user = users.find(u => u.email === email && u.password === password);

    if (!user) {
        alert('Invalid email or password!');
        return;
    }

    localStorage.setItem('currentUser', JSON.stringify(user));

    if (user.role === 'fi') {
        window.location.href = 'fi-dashboard.html';
    } else {
        alert(`Welcome ${user.name}! Dashboard for ${user.role} coming soon.`);
    }
}

function logout() {
    localStorage.removeItem('currentUser');
    alert('You have logged out successfully.');

    if (window.location.pathname.includes('.php')) {
        window.location.href = 'index.php';
    } else {
        window.location.href = 'index.html';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const currentUser = JSON.parse(localStorage.getItem("currentUser"));

    if (currentUser && (window.location.pathname.includes("fi-dashboard.html"))) {

        if (currentUser.role !== "fi") {
            alert("Access denied. Please log in as a Faculty Intern.");
            window.location.href = "index.html";
            return;
        }

        if(document.getElementById("welcomeMessage")) {
            document.getElementById("welcomeMessage").textContent = `Welcome, ${currentUser.name}!`;
        }
        if(document.getElementById("profileName")) {
            document.getElementById("profileName").textContent = currentUser.name;
        }

    }
});