const container = document.getElementById('container');
const registerBtn = document.getElementById('signup');
const loginBtn = document.getElementById('signin');

registerBtn.addEventListener('click', () => {
    container.classList.add("active");
});

loginBtn.addEventListener('click', () => {
    container.classList.remove("active");
});