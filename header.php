<?php
// session_start(); // Uncomment if needed
@include 'config.php';
require_once 'notification_helper.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_image = null;
$user_name = '';
$initial = 'T';
$has_profile_image = false;
$unread_notifications = 0;

if ($user_id) {
  try {
    $stmt = $conn->prepare("SELECT name, image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user['name'] ?? '';
    $initial = strtoupper(substr($user_name ?: 'T', 0, 1));

    // Get unread notification count
    $unread_notifications = getUnreadNotificationCount($user_id);

    if (!empty($user['image'])) {
      // sanitize filename and check file exists in uploaded_img
      $sanitized = htmlspecialchars($user['image']);
      $imagePath = __DIR__ . '/uploaded_img/' . $sanitized;
      if (file_exists($imagePath) && is_file($imagePath)) {
        $user_image = $sanitized;
        $has_profile_image = true;
      }
    }
  } catch (Exception $e) {
    // handle gracefully
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>FreshMart Grocery</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Custom CSS -->
  <link rel="stylesheet" href="assets/style.css">

  <style>
    .hover-shadow:hover {
      box-shadow: 0 0.75rem 1.25rem rgba(0, 0, 0, 0.1) !important;
      transform: scale(1.02);
      transition: 0.3s ease-in-out;
    }
    .object-fit-cover {
      object-fit: cover;
    }
    .transition-hover {
      transition: transform 0.3s ease;
    }
    .card:hover .transition-hover {
      transform: scale(1.05);
    }
  </style>
</head>
<body class="bg-gray-50">

<!-- Fixed Header -->
<nav class="fixed top-0 left-0 w-full z-50 bg-green-600 text-white shadow-md py-1">
  <div class="w-full flex items-center justify-between pl-16 pr-6 py-2">
    <!-- Logo -->
    <a href="/grocery-web-app/index.php" class="text-white text-xl font-semibold italic flex items-center space-x-2">
      <i class="fas fa-shopping-basket"></i>
      <span>FreshMart</span>
    </a>

 <!-- Search Bar -->
<div class="flex-1 mx-4 max-w-md relative">
  <form action="categories.php" method="GET" autocomplete="off" class="flex items-center bg-white rounded-md overflow-hidden h-9">
    <input 
      type="text" 
      name="search" 
      id="searchInput"
      placeholder="Search grocery products"
      class="w-full px-2 py-1 text-sm text-gray-800 focus:outline-none"
    >
    <button type="submit" class="px-2 text-green-600 text-sm">
      <i class="fas fa-search"></i>
    </button>
  </form>

  <!-- Dropdown -->
  <div id="searchDropdown" class="absolute top-full mt-1 w-full bg-white border rounded shadow hidden z-50">
    <!-- Recent & Trending will go here dynamically -->
  </div>
</div>


    <!-- Icons -->
    <div class="flex items-center space-x-10">
      <!--  Updated Delivery Location Button -->
      <div>
        <button onclick="togglePincodeModal()" class="flex items-center space-x-1 bg-green-700 px-3 py-1 rounded hover:bg-green-800 focus:outline-none">
          <i class="fas fa-map-marker-alt"></i>
          <span id="deliveryLocationText">Fetching location...</span>
          <i class="fas fa-chevron-down text-xs ml-1"></i>
        </button>
      </div>

      <?php if ($user_id): ?>
        <!-- Profile Avatar -->
        <div class="flex items-center space-x-12">
          <div class="relative" id="profileDropdownWrapper">
            <button onclick="toggleProfileDropdown()" id="profileButton" class="flex items-center">
              <div class="w-8 h-8 rounded-full overflow-hidden border-2 border-white mt-[4px] bg-gray-100">
                <?php if (!empty($has_profile_image) && $has_profile_image): ?>
                  <img src="uploaded_img/<?= $user_image ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center bg-gray-200 text-green-700 font-semibold text-sm"><?= htmlspecialchars($initial) ?></div>
                <?php endif; ?>
              </div>
            </button>

            <!-- Profile Dropdown -->
            <div id="profileDropdown" class="absolute hidden bg-white text-black mt-2 rounded shadow-lg w-44 right-0 z-50">
              <a href="/grocery-web-app/profile.php" class="flex items-center px-4 py-2 hover:bg-gray-100">
                <i class="fas fa-user text-gray-600 w-5 mr-3"></i> View Profile
              </a>
              <a href="/grocery-web-app/orders.php" class="flex items-center px-4 py-2 hover:bg-gray-100">
                <i class="fas fa-box text-gray-600 w-5 mr-3"></i> My Orders
              </a>
              <a href="/grocery-web-app/wishlist.php" class="flex items-center px-4 py-2 hover:bg-gray-100">
                <i class="fas fa-heart text-gray-600 w-5 mr-3"></i> Wishlist
              </a>
              <a href="/grocery-web-app/offers.php" class="flex items-center px-4 py-2 hover:bg-gray-100">
                <i class="fas fa-tags text-gray-600 w-5 mr-3"></i> Offers
              </a>
              <a href="/grocery-web-app/notifications.php" class="flex items-center px-4 py-2 hover:bg-gray-100 relative">
                <i class="fas fa-bell text-gray-600 w-5 mr-3"></i> 
                Notifications
                <?php if ($unread_notifications > 0): ?>
                  <span class="notification-badge absolute top-1 right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                    <?php echo $unread_notifications > 99 ? '99+' : $unread_notifications; ?>
                  </span>
                <?php endif; ?>
              </a>
              <a href="/grocery-web-app/auth/logout.php" class="flex items-center px-4 py-2 hover:bg-gray-100 text-red-600">
                <i class="fas fa-sign-out-alt text-red-600 w-5 mr-3"></i> Logout
              </a>
            </div>
          </div>
      <?php else: ?>
        <a href="/grocery-web-app/auth/login.php" class="bg-white text-green-600 font-semibold px-4 py-1 rounded hover:bg-green-100 transition">
          Login
        </a>
      <?php endif; ?>

      <!-- Cart -->
      <a href="/grocery-web-app/cart.php" class="flex items-center hover:underline">
        <i class="fas fa-shopping-cart mr-1"></i>
        Cart (<?= function_exists('getCartItemCount') ? getCartItemCount() : '0'; ?>)
      </a>
    </div>
  </div>
</nav>


<div class="relative" id="locationDropdownWrapper">

<div>
  <button onclick="togglePincodeModal()" class="flex items-center space-x-1 bg-green-700 px-3 py-1 rounded hover:bg-green-800 focus:outline-none">
    <i class="fas fa-map-marker-alt"></i>
    <span id="deliveryLocationText" class="whitespace-nowrap">Fetching location...</span>
    <i class="fas fa-chevron-down text-xs ml-1"></i>
  </button>
</div>

<!--  Pincode Modal -->
<div class="relative" id="locationDropdownWrapper">
  <div id="pincodeModal"
       class="absolute top-full mt-2 w-72 bg-white rounded shadow-lg p-4 z-50 hidden"
       style="right: 18%;">
       
    <h2 class="text-base font-bold mb-1">Verify Pincode</h2>
    <p class="text-gray-500 text-sm mb-3">Delivering in select cities</p>
    
    <form onsubmit="return false;" class="flex space-x-2 mb-2">
      <input type="text" id="pincodeInput" maxlength="6" pattern="\d{6}" placeholder="Enter pincode"
        class="w-28 border border-gray-300 px-2 py-1 rounded-l text-sm focus:outline-none" required>
      
      <!--  Clean Location Button -->
      <button type="button" onclick="fetchUserLocation()"
        class="bg-gray-100 px-3 py-1 text-sm rounded-r border-l border-gray-300 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-400 cursor-pointer flex items-center space-x-2 transition">
        <i class="fas fa-crosshairs text-blue-600"></i>
        <span class="text-blue-600">Current Location</span>
      </button>
    </form>

    <span id="pincodeError" class="text-red-600 text-xs"></span>

    <!--  Verify Button -->
    <button onclick="submitPincode()" class="bg-green-600 text-white w-full py-1.5 mt-2 rounded text-sm hover:bg-green-700">
      Verify
    </button>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const locationText = document.getElementById("deliveryLocationText");
    const savedPincode = localStorage.getItem("user_pincode");

    if (savedPincode) {
      locationText.textContent = "Deliver to " + savedPincode;
      document.getElementById("pincodeInput").value = savedPincode;
    } else {
      fetchUserLocation(); // Only fetch if not already saved
    }
  });

  function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('hidden');
  }

  document.addEventListener('click', function (event) {
    const wrapper = document.getElementById('profileDropdownWrapper');
    const dropdown = document.getElementById('profileDropdown');
    if (wrapper && !wrapper.contains(event.target)) {
      dropdown.classList.add('hidden');
    }
  });

  function togglePincodeModal() {
    const modal = document.getElementById('pincodeModal');
    modal.classList.toggle('hidden');
  }

  function submitPincode() {
    const input = document.getElementById('pincodeInput');
    const error = document.getElementById('pincodeError');
    const value = input.value.trim();

    if (value === '') {
      error.textContent = "Please enter pincode";
      input.classList.add('border-red-500');
      return false;
    }

    if (!/^\d{6}$/.test(value)) {
      error.textContent = "Please enter valid pincode";
      input.classList.add('border-red-500');
      return false;
    }

    error.textContent = "";
    input.classList.remove('border-red-500');

    //  Save in localStorage and update UI
    localStorage.setItem("user_pincode", value);
    document.getElementById('deliveryLocationText').textContent = "Deliver to " + value;
    togglePincodeModal();
    return false;
  }

  //  Fetch pincode from LocationIQ using GPS
  function fetchUserLocation() {
    const input = document.getElementById('pincodeInput');
    const locationText = document.getElementById('deliveryLocationText');
    const error = document.getElementById('pincodeError');
    const API_KEY = 'pk.f69eec3be205a7124c4427f5e76c5a77';

    error.textContent = "";
    input.classList.remove('border-red-500');

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        position => {
          const { latitude, longitude } = position.coords;

          fetch(`https://us1.locationiq.com/v1/reverse.php?key=${API_KEY}&lat=${latitude}&lon=${longitude}&format=json`)
            .then(res => res.json())
            .then(data => {
              const pincode = data?.address?.postcode || '';
              if (pincode) {
                input.value = pincode;
                locationText.textContent = "Deliver to " + pincode;
                localStorage.setItem("user_pincode", pincode); // ✅ Save for future
              } else {
                error.textContent = "Could not detect pincode from location.";
                input.classList.add('border-red-500');
              }
            })
            .catch(() => {
              error.textContent = "Failed to fetch address from LocationIQ.";
              input.classList.add('border-red-500');
            });
        },
        () => {
          error.textContent = "Location access denied by user.";
          input.classList.add('border-red-500');
        }
      );
    } else {
      error.textContent = "Geolocation not supported by your browser.";
    }
  }

  //  Optional: Clear saved pincode and refetch
  function resetPincode() {
    localStorage.removeItem("user_pincode");
    document.getElementById("deliveryLocationText").textContent = "Fetching location...";
    fetchUserLocation();
  }

  // Auto-close dropdowns on scroll
  let scrollTimeout;
  window.addEventListener('scroll', function () {
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
      const pincodeModal = document.getElementById('pincodeModal');
      if (pincodeModal && !pincodeModal.classList.contains('hidden')) {
        pincodeModal.classList.add('hidden');
      }

      const profileDropdown = document.getElementById('profileDropdown');
      if (profileDropdown && !profileDropdown.classList.contains('hidden')) {
        profileDropdown.classList.add('hidden');
      }
    }, 100);
  }, { passive: true });

document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('searchInput');
  const suggestionsBox = document.getElementById('suggestionsBox');
  // If a page doesn't include the search UI, bail out to avoid null errors
  if (!input || !suggestionsBox) return;
  let trending = []; // Will be fetched from backend
  let timeout = null;

  // Fetch trending from backend
  fetch('trending.php')
    .then(res => res.json())
    .then(data => {
      if (Array.isArray(data)) trending = data;
    });

  function getHistory() {
    return JSON.parse(localStorage.getItem("search_history") || "[]");
  }

  function setHistory(term) {
    if (!term) return;
    let history = getHistory();
    history = history.filter(t => t.toLowerCase() !== term.toLowerCase());
    history.unshift(term);
    if (history.length > 5) history = history.slice(0, 5);
    localStorage.setItem("search_history", JSON.stringify(history));
  }

  function buildSuggestions(query, suggestions) {
    const history = getHistory().filter(item => item.toLowerCase().includes(query.toLowerCase()));
    let html = '';

    if (history.length > 0) {
      html += `<li class="px-3 py-2 text-xs text-gray-400">Recent Searches</li>`;
      history.forEach(item => {
        html += `
          <li class="px-3 py-2 flex justify-between items-center hover:bg-gray-100 cursor-pointer recent-item text-sm text-gray-800">
            <div class="flex items-center gap-2">
              <i class="fas fa-history text-gray-400"></i> ${item}
            </div>
            <a href="categories.php?search=${encodeURIComponent(item)}" class="text-blue-500 text-xs">Search</a>
          </li>
        `;
      });
    }

    if (suggestions.length > 0) {
      html += `<li class="px-3 py-2 text-xs text-gray-400">Suggestions</li>`;
      suggestions.forEach(item => {
        html += `<li class="px-3 py-2 hover:bg-green-100 cursor-pointer suggestion-item text-sm">${item}</li>`;
      });
    }

    if (trending.length > 0 && !query) {
      html += `<li class="px-3 py-2 text-xs text-gray-400">Trending</li>`;
      trending.forEach(item => {
        html += `
          <li class="px-3 py-2 flex items-center gap-2 hover:bg-gray-100 cursor-pointer trending-item text-sm text-gray-800">
            <i class="fas fa-search text-gray-400"></i> ${item}
          </li>
        `;
      });
    }

    return html || `<li class="px-3 py-2 text-gray-500 text-sm">No suggestions found</li>`;
  }

  input.addEventListener('input', () => {
    const query = input.value.trim();

    clearTimeout(timeout);
    if (query.length < 2) {
      suggestionsBox.innerHTML = buildSuggestions('', []);
      suggestionsBox.classList.remove('hidden');
      return;
    }

    timeout = setTimeout(() => {
      fetch(`search_suggestions.php?term=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
          suggestionsBox.innerHTML = buildSuggestions(query, data);
          suggestionsBox.classList.remove('hidden');
        });
    }, 300);
  });

  input.addEventListener('focus', () => {
    const query = input.value.trim();
    if (query.length < 2) {
      suggestionsBox.innerHTML = buildSuggestions('', []);
      suggestionsBox.classList.remove('hidden');
    }
  });

  document.addEventListener('click', (e) => {
    if (!suggestionsBox.contains(e.target) && e.target !== input) {
      suggestionsBox.classList.add('hidden');
    }
  });

  suggestionsBox.addEventListener('click', (e) => {
    const item = e.target.closest('.suggestion-item, .recent-item, .trending-item');
    if (item) {
      const term = item.textContent.trim();
      input.value = term;
      setHistory(term);
      input.form.submit();
    }
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const term = input.value.trim();
      setHistory(term);
    }
  });
});
</script>