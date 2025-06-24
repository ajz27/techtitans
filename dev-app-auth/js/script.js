window.addEventListener('DOMContentLoaded', () => {
    console.log("Script loaded and DOM is ready.");
  
    const nav = document.getElementById('navbar');
    console.log("Navbar element:", nav);
    if (!nav) {
      console.log("Navbar not found.");
      return;
    }
  
    const isLoggedIn = localStorage.getItem('loggedIn') === 'true';
    console.log("isLoggedIn:", isLoggedIn);
  
    if (isLoggedIn) {
      nav.innerHTML = `
        <a class="nav-link" href="index.php">Home</a>
        <a class="nav-link" href="profile.php">Profile</a>
        <a class="nav-link" href="dashboard.html">Dashboard</a>
        <a class="nav-link" href="logout.php">Logout</a>
      `;
    } else {
      nav.innerHTML = `
        <a class="nav-link" href="login.html">Login</a>
        <a class="nav-link" href="register.html">Register</a>
      `;
    }
  });
  