<!-- Top Navigation -->
<nav class="bg-teal-600 p-4 text-white shadow-md relative z-30">
  <div class="container mx-auto flex justify-between items-center">
    <!-- Mobile menu button -->
    <button id="sidebarToggle" class="md:hidden mr-4 text-white focus:outline-none">
      <i class="fas fa-bars text-xl"></i>
    </button>

    <!-- Logo and Ward Info -->
    <div class="flex items-center">
      <a href="dashboard.html" class="flex items-center">
        <i class="fas fa-hospital-alt text-2xl mr-3"></i>
        <div>
          <h1 class="text-xl font-bold">Nurse Dashboard</h1>
          <p class="text-sm opacity-80">
            <i class="fas fa-map-marker-alt mr-1"></i> Pediatric Ward
          </p>
        </div>
      </a>
    </div>

    <!-- Desktop Navigation -->
    <div class="hidden md:flex space-x-4 items-center">
      <a href="dashboard.html" class="hover:bg-teal-500 px-3 py-1 rounded transition bg-teal-700">
        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
      </a>

      <a href="messages.html" class="hover:bg-teal-500 px-3 py-1 rounded transition">
        <i class="fas fa-envelope mr-2"></i> Messages
      </a>

      <a href="patients.html" class="hover:bg-teal-500 px-3 py-1 rounded transition">
        <i class="fas fa-procedures mr-2"></i> Patients
      </a>

      <a href="emergency.html" class="hover:bg-teal-500 px-3 py-1 rounded transition">
        <i class="fas fa-exclamation-triangle mr-2"></i> Emergency
      </a>
    </div>

    <!-- User Profile and Logout -->
    <div class="flex items-center space-x-4">
      <div class="text-right hidden md:block">
        <p class="font-medium">Jabulani</p>
        <p class="text-xs opacity-75">
          <i class="fas fa-user-shield mr-1"></i>
          Nurse
        </p>
      </div>
      <a href="logout.html" class="hover:bg-teal-500 px-3 py-1 rounded transition flex items-center">
        <i class="fas fa-sign-out-alt mr-2"></i> 
        <span class="hidden md:inline">Logout</span>
      </a>
    </div>
  </div>

  <!-- Mobile Back Button -->
  <button onclick="window.history.back()" class="md:hidden absolute left-16 top-1/2 transform -translate-y-1/2 text-white">
    <i class="fas fa-arrow-left text-xl"></i>
  </button>
</nav>

<!-- Mobile Sidebar -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" id="sidebarOverlay"></div>
<div class="fixed left-0 top-0 h-full w-64 bg-white shadow-lg transform -translate-x-full transition-transform duration-300 z-50 md:hidden" id="sidebar">
  <div class="p-4 bg-teal-600 text-white flex justify-between items-center">
    <h3 class="text-lg font-bold">Menu</h3>
    <button id="sidebarClose" class="text-white focus:outline-none">
      <i class="fas fa-times text-xl"></i>
    </button>
  </div>
  <ul class="py-2">
    <li>
      <a href="dashboard.html" class="flex items-center p-3 hover:bg-gray-100 text-gray-800 bg-teal-50">
        <i class="fas fa-tachometer-alt mr-3 text-teal-600 w-6 text-center"></i>
        Dashboard
      </a>
    </li>

    <li>
      <a href="messages.html" class="flex items-center p-3 hover:bg-gray-100 text-gray-800">
        <i class="fas fa-envelope mr-3 text-teal-600 w-6 text-center"></i>
        Messages
      </a>
    </li>

    <li>
      <a href="patients.html" class="flex items-center p-3 hover:bg-gray-100 text-gray-800">
        <i class="fas fa-procedures mr-3 text-teal-600 w-6 text-center"></i>
        Patients
      </a>
    </li>

    <li>
      <a href="emergency.html" class="flex items-center p-3 hover:bg-gray-100 text-gray-800">
        <i class="fas fa-exclamation-triangle mr-3 text-teal-600 w-6 text-center"></i>
        Emergency
      </a>
    </li>

    <li class="border-t mt-2 pt-2">
      <a href="logout.html" class="flex items-center p-3 hover:bg-gray-100 text-gray-800">
        <i class="fas fa-sign-out-alt mr-3 text-teal-600 w-6 text-center"></i>
        Logout
      </a>
    </li>
  </ul>
</div>

<!-- Sidebar Script -->
<script>
  // Mobile sidebar toggle
  document.getElementById('sidebarToggle').addEventListener('click', function () {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  });

  document.getElementById('sidebarClose').addEventListener('click', function () {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  });

  document.getElementById('sidebarOverlay').addEventListener('click', function () {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  });
</script>
