<?php
$db = new SQLite3('series.db');

$db->exec("CREATE TABLE IF NOT EXISTS series (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cover TEXT,
    title TEXT NOT NULL,
    season INTEGER,
    episode INTEGER,
    rating INTEGER DEFAULT 0
)");

$result = $db->query("PRAGMA table_info(series)");
$hasRating = false;
while ($column = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($column['name'] === 'rating') {
        $hasRating = true;
        break;
    }
}
if (!$hasRating) {
    $db->exec("ALTER TABLE series ADD COLUMN rating INTEGER DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $title = $_POST['title'];
    $cover = $_POST['cover'];
    $season = $_POST['season'];
    $episode = $_POST['episode'];
    $rating = $_POST['rating'];

    $stmt = $db->prepare("INSERT INTO series (cover, title, season, episode, rating) VALUES (:cover, :title, :season, :episode, :rating)");
    $stmt->bindValue(':cover', $cover, SQLITE3_TEXT);
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':episode', $episode, SQLITE3_INTEGER);
    $stmt->bindValue(':rating', $rating, SQLITE3_INTEGER);
    $stmt->execute();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $db->exec("DELETE FROM series WHERE id = $id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $cover = $_POST['cover'];
    $season = $_POST['season'];
    $episode = $_POST['episode'];
    $rating = $_POST['rating'];

    $stmt = $db->prepare("UPDATE series SET cover = :cover, title = :title, season = :season, episode = :episode, rating = :rating WHERE id = :id");
    $stmt->bindValue(':cover', $cover, SQLITE3_TEXT);
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':season', $season, SQLITE3_INTEGER);
    $stmt->bindValue(':episode', $episode, SQLITE3_INTEGER);
    $stmt->bindValue(':rating', $rating, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rating'])) {
    $id = $_POST['series_id'];
    $rating = $_POST['rating_value'];
    
    $stmt = $db->prepare("UPDATE series SET rating = :rating WHERE id = :id");
    $stmt->bindValue(':rating', $rating, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

$editData = [];
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $editData = $db->querySingle("SELECT * FROM series WHERE id = $id", true);
}

$search = $_GET['search'] ?? '';
$query = "SELECT * FROM series";
if ($search) {
    $query .= " WHERE title LIKE '%$search%'";
}
$series = $db->query($query);

$totalRecords = $db->querySingle("SELECT COUNT(*) FROM series");
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>სერიალების მენეჯერი</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
        }
        
        .container {
            max-width: 1200px;
            padding: 20px;
        }
        
        h1 {
            margin-bottom: 30px;
            color: #0d6efd;
            font-weight: 600;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }
        
        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 1001;
            display: none;
            width: 90%;
            max-width: 500px;
        }
        
        .table img, .card-img-top {
            aspect-ratio: 9/16;
            object-fit: cover;
            width: 100%;
            border-radius: 5px;
        }
        
        .card {
            height: 100%;
            transition: transform 0.3s;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card-title {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .card-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .card-footer {
            background-color: white;
            border-top: 1px solid rgba(0,0,0,.125);
            padding: 0.75rem 1.25rem;
        }
        
        .rating {
            display: flex;
            justify-content: center;
            margin: 10px 0;
        }
        
        .rating i {
            color: #ffcb00;
            cursor: pointer;
            padding: 2px;
            font-size: 1.5rem;
        }
        
        .rating i.active {
            color: #ffc107;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                display: none; 
            }
            
            .card-container {
                margin-top: 20px;
            }
            
            .popup {
                width: 95%;
                padding: 20px;
            }
        }
        
        @media (min-width: 769px) {
            .card-container {
                display: none; 
            }
        }
        
        .count {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        .btn-success {
            background-color: #198754;
            border-color: #198754;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }
        
        .search-form {
            margin-bottom: 20px;
        }
        
        .search-form .input-group {
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">სერიალების მენეჯერი</h1>
        
        <div class="text-center mb-3">
            <button class="btn btn-primary" onclick="openPopup('add')">სერიალის დამატება</button>
        </div>

        <form method="GET" class="mb-3 search-form">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="მოძიება დასახელებით" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-outline-primary">ძიება</button>
            </div>
            <div class="text-center mt-3 mb-3 count">
                <strong>ჩანაწერების ოდენობა : <?= $totalRecords ?></strong>
            </div>
        </form>

        <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-primary">
                        <tr>
                            <th>ყრდა</th>
                            <th>დასახელება</th>
                            <th>სეზონი</th>
                            <th>ეპიზოდი</th>
                            <th>შეფასება</th>
                            <th>ქმედება</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $series->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td style="width: 130px;">
                                <img src="<?= htmlspecialchars($row['cover']) ?>" alt="ყრდა" class="img-fluid">
                            </td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['season']) ?></td>
                            <td><?= htmlspecialchars($row['episode']) ?></td>
                            <td>
                                <div class="rating" data-series-id="<?= $row['id'] ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa<?= ($i <= $row['rating']) ? 's' : 'r' ?> fa-star" data-rating="<?= $i ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td>
                                <a href="?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm mb-1">ჩასწორება</a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('ნამდვილად გსურს წაშლა ?')">წაშლა</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
            
        <div class="d-md-none">
            <div class="row row-cols-1 row-cols-sm-2 g-4 card-container">
                <?php
                $series = $db->query($query);
                while ($row = $series->fetchArray(SQLITE3_ASSOC)):
                ?>
                <div class="col">
                    <div class="card h-100">
                        <img src="<?= htmlspecialchars($row['cover']) ?>" class="card-img-top" alt="ყრდა">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
                            <h6 class="card-subtitle mb-2">სეზონი: <?= htmlspecialchars($row['season']) ?>, ეპიზოდი: <?= htmlspecialchars($row['episode']) ?></h6>
                            <div class="rating" data-series-id="<?= $row['id'] ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa<?= ($i <= $row['rating']) ? 's' : 'r' ?> fa-star" data-rating="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="?edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm">ჩასწორება</a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('ნამდვილად გსურს წაშლა ?')">წაშლა</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div id="popup" class="popup">
            <form method="POST">
                <h3 class="text-center mb-4"><?= isset($editData['id']) ? 'სერიალის ჩასოწრება' : 'ახალი სერიალის დამატება' ?></h3>
                <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">
                <div class="mb-3">
                    <label for="cover" class="form-label">ყრდა (URL):</label>
                    <input type="text" id="cover" name="cover" class="form-control" value="<?= $editData['cover'] ?? '' ?>" required>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">დასახელება:</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?= $editData['title'] ?? '' ?>" required>
                </div>
                <div class="mb-3">
                    <label for="season" class="form-label">სეზონი:</label>
                    <input type="number" id="season" name="season" min="1" class="form-control" value="<?= $editData['season'] ?? 1 ?>" required>
                </div>
                <div class="mb-3">
                    <label for="episode" class="form-label">ეპიზოდი:</label>
                    <input type="number" id="episode" name="episode" min="1" class="form-control" value="<?= $editData['episode'] ?? 1 ?>" required>
                </div>
                <div class="mb-3">
                    <label for="rating" class="form-label">შეფასება ( 0 / 5):</label>
                    <input type="number" id="rating" name="rating" min="0" max="5" class="form-control" value="<?= $editData['rating'] ?? 0 ?>" required>
                </div>
                <div class="text-center">
                    <button type="submit" name="<?= isset($editData['id']) ? 'edit' : 'add' ?>" class="btn btn-success">
                        <?= isset($editData['id']) ? 'ცვლილების შენახვა' : 'დამატება' ?>
                    </button>
                    <button type="button" onclick="closePopup()" class="btn btn-secondary">დახურვა</button>
                </div>
            </form>
        </div>

        <div id="overlay" class="overlay"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPopup(mode) {
            document.getElementById('popup').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        }

        function closePopup() {
            document.getElementById('popup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
            window.location.href = window.location.pathname;
        }

        <?php if (isset($_GET['edit'])): ?>
        openPopup('edit');
        <?php endif; ?>

        $(document).ready(function() {
            $('.rating i').hover(
                function() {
                    var rating = $(this).data('rating');
                    var parentRating = $(this).parent();
                    
                    parentRating.find('i').each(function(index) {
                        if (index < rating) {
                            $(this).addClass('fas').removeClass('far');
                        } else {
                            $(this).addClass('far').removeClass('fas');
                        }
                    });
                },
                function() {
                    var parentRating = $(this).parent();
                    var seriesId = parentRating.data('series-id');
                    
                    var currentRating = parentRating.data('current-rating') || 0;
                    
                    parentRating.find('i').each(function(index) {
                        if (index < currentRating) {
                            $(this).addClass('fas').removeClass('far');
                        } else {
                            $(this).addClass('far').removeClass('fas');
                        }
                    });
                }
            );
            
            $('.rating i').click(function() {
                var rating = $(this).data('rating');
                var parentRating = $(this).parent();
                var seriesId = parentRating.data('series-id');
                
                parentRating.data('current-rating', rating);
                
                parentRating.find('i').each(function(index) {
                    if (index < rating) {
                        $(this).addClass('fas').removeClass('far');
                    } else {
                        $(this).addClass('far').removeClass('fas');
                    }
                });
                
                $.ajax({
                    type: 'POST',
                    url: window.location.pathname,
                    data: {
                        update_rating: true,
                        series_id: seriesId,
                        rating_value: rating
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            console.log('რეიტინგი განახლდა');
                        }
                    },
                    error: function() {
                        console.error('შეცდომა რეიტინგის განახლებისას !');
                    }
                });
            });
            
            $('.rating').each(function() {
                var activeStars = $(this).find('i.fas').length;
                $(this).data('current-rating', activeStars);
            });
        });
    </script>
</body>
</html>
