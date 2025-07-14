<?php
require "../assets/php/session_check.php";
require "../assets/php/conn.php";

require_once '../assets/php/session_print.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = [
        'type' => 'danger',
        'message' => 'You must be logged in.'
    ];
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is a regional manager and fetch their regions
$isRegionalManager = false;
$regions = [];

$stmt = $mysqli->prepare("SELECT regions.id, regions.name 
                          FROM regions 
                          INNER JOIN user_region_roles 
                          ON regions.id = user_region_roles.region_id 
                          WHERE user_region_roles.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $regions[] = $row;
}
$stmt->close();

if (!empty($regions)) $isRegionalManager = true;

// Fetch all venues (for Join/Leave table)
$allVenueResult = $mysqli->query("SELECT id, name, location FROM venues");
$allVenues = $allVenueResult->fetch_all(MYSQLI_ASSOC);

// Fetch player’s joined venue IDs
$joinedVenueIds = [];
$joinedResult = $mysqli->query("SELECT venue_id FROM player_venues WHERE user_id = $user_id");
if ($joinedResult) {
    $joinedVenueIds = array_column($joinedResult->fetch_all(MYSQLI_ASSOC), 'venue_id');
}

// If regional manager, fetch their venues with full details
$venues = [];
if ($isRegionalManager) {
    $regionIds = implode(',', array_column($regions, 'id'));
    $venueQuery = "SELECT id, name, location, marker_color, region_id,
                          ST_X(location_point) AS longitude, 
                          ST_Y(location_point) AS latitude 
                   FROM venues 
                   WHERE region_id IN ($regionIds)";
    $venueResult = $mysqli->query($venueQuery);
    while ($venueRow = $venueResult->fetch_assoc()) {
        $venues[] = $venueRow;
    }
}

// Check if user is a Tournament Director and fetch their venues
$isTournamentDirector = false;
$tdVenues = [];

$stmt = $mysqli->prepare("SELECT venues.id, venues.name, venues.location 
                          FROM venues
                          INNER JOIN user_venue_roles 
                          ON venues.id = user_venue_roles.venue_id
                          WHERE user_venue_roles.user_id = ? AND user_venue_roles.role = 'tournament_director'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tdVenues[] = $row;
}
$stmt->close();

if (!empty($tdVenues)) $isTournamentDirector = true;
?>

<?php
// Display toast if exists
if (isset($_SESSION['toast'])): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
    var toastHTML = `<div class='toast bg-<?php echo $_SESSION['toast']['type']; ?> text-white' role='alert' aria-live='assertive' aria-atomic='true' data-delay='5000'>
        <div class='toast-header'>
            <strong class='mr-auto'>Message</strong>
            <small>now</small>
            <button type='button' class='ml-2 mb-1 close' data-dismiss='toast' aria-label='Close'>
                <span aria-hidden='true'>&times;</span>
            </button>
        </div>
        <div class='toast-body'><?php echo addslashes($_SESSION['toast']['message']); ?></div>
    </div>`;
    document.getElementById('toast-container').innerHTML = toastHTML;
    $('.toast').toast('show');
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>inPubpoker Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
</head>

<body id="page-top">
<div id="wrapper">

    <!-- Sidebar -->
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
            <div class="sidebar-brand-icon rotate-n-15"><i class="fas fa-laugh-wink"></i></div>
            <div class="sidebar-brand-text mx-3">inPubpoker</div>
        </a>
        <hr class="sidebar-divider my-0">
        <li class="nav-item active"><a class="nav-link" href="index.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Addons</div>
        <li class="nav-item"><a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages"><i class="fas fa-fw fa-folder"></i><span>Pages</span></a>
            <div id="collapsePages" class="collapse">
                <div class="bg-white py-2 collapse-inner rounded">
                    <h6 class="collapse-header">Login Screens:</h6>
                    <a class="collapse-item" href="login.html">Login</a>
                    <a class="collapse-item" href="register.html">Register</a>
                    <a class="collapse-item" href="forgot-password.html">Forgot Password</a>
                    <div class="collapse-divider"></div>
                    <h6 class="collapse-header">Other Pages:</h6>
                    <a class="collapse-item" href="404.html">404 Page</a>
                    <a class="collapse-item" href="blank.html">Blank Page</a>
                </div>
            </div>
        </li>
        <hr class="sidebar-divider d-none d-md-block">
        <div class="text-center d-none d-md-inline"><button class="rounded-circle border-0" id="sidebarToggle"></button></div>
    </ul>
    <!-- End Sidebar -->

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['firstname'].' '.$_SESSION['lastname']); ?></span>
                            <img class="img-profile rounded-circle" src="img/undraw_profile.svg">
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="#"><i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>Profile</a>
                            <a class="dropdown-item" href="#"><i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>Settings</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal"><i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>Logout</a>
                        </div>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800">Dashboard</h1>
                <p class="mb-4">If you are not seeing anything below here, don't worry, we are still working on the players' features. <br><br> If you are a Tournament Director or a Venue Manager and still do not see anything below then something went wrong, please get in touch.</p>

                <!-- Venues Join/Leave Table -->
                <table class="table table-bordered">
                <thead>
                    <tr>
                    <th>Venue Name</th>
                    <th>Address</th>
                    <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allVenues as $venue): 
                    $joined = in_array($venue['id'], $joinedVenueIds); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($venue['name']); ?></td>
                        <td><?php echo htmlspecialchars($venue['location']); ?></td>
                        <td>
                        <?php if ($joined): ?>
                            <form method="POST" action="../assets/php/leave_venue.php" style="display:inline;">
                            <input type="hidden" name="venue_id" value="<?php echo $venue['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Leave</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="../assets/php/join_venue.php" style="display:inline;">
                            <input type="hidden" name="venue_id" value="<?php echo $venue['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm">Join</button>
                            </form>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>

                <!-- Add Venue section for Regional Manager -->
                <?php if ($isRegionalManager): ?>
                    <h3 class="h4 mb-4">Add/Edit Venues (as Regional Manager)</h3>

                    <!-- Venues Table with full details -->
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Marker Color</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($venues as $venue): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($venue['name']); ?></td>
                                <td><?php echo htmlspecialchars($venue['location']); ?></td>
                                <td><?php echo htmlspecialchars($venue['marker_color']); ?></td>
                                <td><?php echo $venue['latitude']; ?></td>
                                <td><?php echo $venue['longitude']; ?></td>

                                <td>
                                    <button type="button"
                                        class="btn btn-sm btn-warning edit-venue-btn"
                                        data-toggle="modal"
                                        data-target="#editVenueModal"
                                        data-id="<?php echo $venue['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($venue['name']); ?>"
                                        data-location="<?php echo htmlspecialchars($venue['location']); ?>"
                                        data-marker_color="<?php echo htmlspecialchars($venue['marker_color']); ?>"
                                        data-latitude="<?php echo $venue['latitude']; ?>"
                                        data-longitude="<?php echo $venue['longitude']; ?>"
                                        data-region_id="<?php echo $venue['region_id']; ?>">
                                        Edit
                                    </button>
                                </td>

                                <td>
                                    <form method="POST" action="../assets/php/delete_venue.php" onsubmit="return confirm('Delete this venue?');">
                                        <input type="hidden" name="id" value="<?php echo $venue['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Add Venue Button -->
                    <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addVenueModal"><i class="fas fa-plus"></i> Add Venue</button>

                <?php endif; ?>


                <!-- TD's Venues table -->
                <?php if ($isTournamentDirector): ?>
                    <h3 class="h4 mb-4">Venues You Manage (as Tournament Director)</h3>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Venue Name</th>
                                <th>Address</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tdVenues as $venue): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($venue['name']); ?></td>
                                <td><?php echo htmlspecialchars($venue['location']); ?></td>
                                <td>
                                    <button 
                                        class="btn btn-primary btn-sm manage-venue-btn"
                                        data-toggle="modal"
                                        data-target="#manageVenueModal"
                                        data-venueid="<?php echo $venue['id']; ?>"
                                        data-venuename="<?php echo htmlspecialchars($venue['name']); ?>"
                                    >
                                        Manage
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>




            </div>
        </div>



        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto text-center">
                <span>Copyright &copy; inPubpoker 2025</span>
            </div>
        </footer>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="logoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Ready to Leave?</h5></div>
            <div class="modal-body">Click logout to end your session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a class="btn btn-primary" href="../assets/php/usersys/logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- Add Venue Modal -->
<div class="modal fade" id="addVenueModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="../assets/php/add_venue.php" method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Venue</h5></div>
            <div class="modal-body">
                <select name="region_id" class="form-control mb-2" required>
                    <option value="">Select Region</option>
                    <?php foreach($regions as $region): ?>
                        <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="name" class="form-control mb-2" placeholder="Venue Name" required>
                <input type="text" name="location" class="form-control mb-2" placeholder="Address" required>
                <input type="text" name="marker_color" class="form-control mb-2" placeholder="Marker Color (optional)">
                <input type="number" step="any" name="latitude" class="form-control mb-2" placeholder="Latitude" required>
                <input type="number" step="any" name="longitude" class="form-control mb-2" placeholder="Longitude" required>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Add Venue</button>
            </div>
        </form>
    </div>
</div>
<!-- Edit Venue Modal -->
<div class="modal fade" id="editVenueModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form action="../assets/php/edit_venue.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Venue</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">

        <input type="hidden" name="id" id="editVenueId">

        <!-- Same fields as Add Venue -->
        <div class="form-group">
          <label>Region</label>
          <select name="region_id" id="editRegionId" class="form-control" required>
            <option value="">Select Region</option>
            <?php foreach ($regions as $r): ?>
            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" id="editVenueName" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Location</label>
          <input type="text" name="location" id="editVenueLocation" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Marker Color</label>
          <input type="text" name="marker_color" id="editMarkerColor" class="form-control">
        </div>
        <div class="form-group">
          <label>Latitude</label>
          <input type="number" step="any" name="latitude" id="editLatitude" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Longitude</label>
          <input type="number" step="any" name="longitude" id="editLongitude" class="form-control" required>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Manage Venue Modal -->
<div class="modal fade" id="manageVenueModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="manageVenueModalLabel">Manage Venue</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <h5 id="manageVenueName" class="mb-3"></h5>

        <div class="accordion" id="venueManageAccordion">

            <!-- Venue Games -->
            <div class="card">
            <div class="card-header" id="headingGames">
                <h6 class="mb-0">
                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseGames" aria-expanded="true" aria-controls="collapseGames">
                    📅 Venue Games
                </button>
                </h6>
            </div>

            <div id="collapseGames" class="collapse" data-parent="#venueManageAccordion">
                <div class="card-body">

                <!-- Season Select Dropdown -->
                <div class="form-group">
                    <label for="gamesSeasonSelect">Season</label>
                    <select id="gamesSeasonSelect" class="form-control form-control-sm">
                    <option value="active" selected>Active Season</option>
                    <!-- dynamically loaded options will be appended here -->
                    </select>
                </div>

                <!-- Games List Table -->
                <div id="venueGamesList">
                    <p>Loading games...</p>
                </div>

                <hr>
                <button class="btn btn-sm btn-primary" id="addGamesBtn">Add Games</button>

                </div>
            </div>
            </div>


          <!-- Venue Players Card-->
          <div class="card">
            <div class="card-header" id="headingPlayers">
              <h6 class="mb-0">
                <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapsePlayers">
                  🧑‍🤝‍🧑 Venue Players
                </button>
              </h6>
            </div>
            <div id="collapsePlayers" class="collapse" data-parent="#venueManageAccordion">
              <div class="card-body">
                <div id="venuePlayersList">Loading players...</div>
              </div>
            </div>
          </div>


    <!-- Scores Card-->
    <div class="card">
    <div class="card-header" id="headingResults">
        <h6 class="mb-0">
        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseResults">
            📝 Scores
        </button>
        </h6>
    </div>

  <div id="collapseResults" class="collapse" data-parent="#venueManageAccordion">
    <div class="card-body">

      <!-- Scores Filter -->
        <div class="form-group">
        <label for="scoresFilterSelect">Show games:</label>
        <select id="scoresFilterSelect" class="form-control form-control-sm">
            <option value="last_game" selected>Last Game</option>
            <option value="this_season">This Season</option>
        </select>
        </div>

      <!-- Scores Table -->
      <div id="venueScoresList">
        <p>Loading scores...</p>
      </div>

    </div>
  </div>
</div>
<!-- end of scores card-->

<!-- Add Scores Modal -->
<div class="modal fade" id="addScoresModal" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 id="addScoresModalLabel" class="modal-title">Add points</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <div class="row">
          <!-- Available Players -->
          <div class="col-md-6 d-flex flex-column">
            <h6>Available Players</h6>
            <div class="flex-grow-1 d-flex flex-column">
              <div class="d-flex border-bottom pb-1 mb-2 font-weight-bold">
                <div class="flex-grow-1">Name (Username)</div>
              </div>
              <ul id="availablePlayersList" class="list-group connectedPlayerList sortable-container flex-grow-1"></ul>
            </div>
          </div>

          <!-- Attending Players -->
          <div class="col-md-6 d-flex flex-column">
            <h6>Attending & Scores</h6>
            <div class="flex-grow-1 d-flex flex-column">
              <div class="d-flex border-bottom pb-1 mb-2 font-weight-bold">
                <div class="flex-grow-1">Name (Username)</div>
              </div>
              <ul id="attendingPlayersList" class="list-group connectedPlayerList sortable-container flex-grow-1"></ul>
            </div>
          </div>
        </div>


      <div class="modal-footer">
        <button type="button" id="saveScoresBtn" class="btn btn-success">💾 Save Scores</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>

    </div>
  </div>
</div>



<!-- JS -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>
<script src="vendor/chart.js/Chart.min.js"></script>
<script src="../assets/js/venue-modals.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<!-- Toast container for dynamic toasts -->
<div id="toast-container" style="position: fixed; bottom: 1rem; right: 1rem; z-index: 1080;"></div>

</body>
</html>
