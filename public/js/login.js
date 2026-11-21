
const loginForm = document.getElementById("login-form");
const rollInput = document.getElementById("login-roll");
const passwordInput = document.getElementById("login-password");
const rollError = document.getElementById("login-roll-error");
const passwordError = document.getElementById("login-password-error");
const loginStatus = document.getElementById("login-status");
const loginBtn = document.getElementById("login-btn");

async function prepareForm() {
    try {
        const data = await apiRequest("me.php");

        if (data.user) {
            window.location.replace("index.html");
            return;
        }

        loginBtn.disabled = false;
        loginBtn.textContent = "Sign in";

    } catch (error) {
        loginStatus.textContent = error.message;
    }
}


loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    // 1. Clear previous errors
    rollError.textContent = "";
    passwordError.textContent = "";
    loginStatus.textContent = "";

    // 2. Get input values
    const roll = rollInput.value.trim();
    const password = passwordInput.value;

    // 3. Loading state
    loginBtn.disabled = true;
    loginBtn.textContent = "Signing in…";

    try {
        // 4. Send login request
        await apiRequest("login.php", {
            method: "POST",
            body: {
                roll_number: roll,
                password: password
            }
        });

        // 5. Redirect after successful login
        window.location.replace("index.html");

    } catch (error) {

        // 6. Display general error
        loginStatus.textContent = error.message;

        // 7. Display individual field errors
        if (error.fields) {
            if (error.fields.roll_number) {
                rollError.textContent = error.fields.roll_number;
            }

            if (error.fields.password) {
                passwordError.textContent = error.fields.password;
            }
        }

        // 8. Clear password field
        passwordInput.value = "";

        // 9. Restore login button
        loginBtn.disabled = false;
        loginBtn.textContent = "Sign in";
    }
});


// Run when the page loads
prepareForm();
