<?php
// Database connection
$host = 'localhost';
$dbname = 'sign_language';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Could not connect to the database $dbname :" . $e->getMessage());
}

// Fetch courses
$stmt = $pdo->query("SELECT * FROM courses");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct categories
$categoryStmt = $pdo->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Pagination
$itemsPerPage = 9;
$totalCourses = count($courses);
$totalPages = ceil($totalCourses / $itemsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Category filter
$currentCategory = isset($_GET['category']) ? $_GET['category'] : '';
if ($currentCategory) {
    $filteredCourses = array_filter($courses, function($course) use ($currentCategory) {
        return $course['category'] === $currentCategory;
    });
} else {
    $filteredCourses = $courses;
}

$pagedCourses = array_slice($filteredCourses, $offset, $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Catalog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#4A90E2',                        'primary-dark': '#2A69A4',
                        'secondary': '#7ED321',
                        'accent': '#F5A623',
                        'success': '#10B981',
                        'warning': '#F1C40F',
                        'error': '#E74C3C',
                        'background': '#f8fafb',
                        'surface': '#FFFFFF',
                        'text': '#333333',
                        'text-light': '#7F8C8D',
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes saveAnimation {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .save-animation {
            animation: saveAnimation 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-background font-sans">
    <div class="max-w-7xl mx-auto pt-4">
        <!-- <header class="mb-8 text-center">
            <h1 class="text-3xl md:text-4xl font-bold text-text">Course Catalog</h1>
            <p class="text-text-light mt-2">Explore our wide range of courses</p>
        </header> -->
        
        <!-- Category Filter -->
        <div id="categoryFilter" class="mb-6 flex flex-wrap justify-center gap-2">
            <a href="?category=" class="px-4 py-2 border rounded <?php echo $currentCategory === '' ? 'bg-primary text-white' : 'bg-white text-primary'; ?>">
                All Categories
            </a>
            <?php foreach ($categories as $category): ?>
                <a href="?category=<?php echo urlencode($category); ?>" class="px-4 py-2 border rounded <?php echo $currentCategory === $category ? 'bg-primary text-white' : 'bg-white text-primary'; ?>">
                    <?php echo htmlspecialchars($category); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Courses Grid -->
        <div id="coursesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($pagedCourses as $course): ?>
                <a href="<?php echo '/courseDetails?course='.htmlspecialchars($course['id']) ;?>" class="bg-white p-4 rounded-lg shadow-md relative ">
                    <img src="<?php echo $course['thumbnail_url'] ? htmlspecialchars($course['thumbnail_url']) : 'https://via.placeholder.com/300x200.png?text=Course+Thumbnail'; ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-full h-40 object-cover rounded-lg">
                    <h2 class="text-xl font-semibold mt-1"><?php echo htmlspecialchars($course['title']); ?></h2>
                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($course['category'] ?? 'Uncategorized'); ?></p>
                    <p class="mt-1 text-gray-800"><?php echo htmlspecialchars(substr($course['description'], 0, 80)) . '...'; ?></p>
                    <div class="flex justify-between items-center mt-2 absolute right-3 top-2">
                        <!-- <button class="px-3 py-1 bg-primary text-white rounded hover:bg-primary-dark transition-colors">
                            Enroll Now
                        </button> -->
                        <button onclick="toggleSave(<?php echo $course['id']; ?>)" class=" p-2 rounded-full hover:bg-gray-100 transition-colors focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 transition-colors duration-300 ease-in-out text-blue-400" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                            </svg>
                        </button>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="mt-8 flex justify-center space-x-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $currentCategory ? '&category=' . urlencode($currentCategory) : ''; ?>" 
                   class="px-4 py-2 border rounded <?php echo $i == $currentPage ? 'bg-primary text-white' : 'bg-white text-primary'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        function toggleSave(courseId) {
            const button = document.querySelector(`button[onclick="toggleSave(${courseId})"]`);
            const icon = button.querySelector('svg');
            
            // Toggle visual state
            icon.classList.toggle('text-primary');
            icon.classList.toggle('fill-current');
            icon.classList.add('save-animation');
            setTimeout(() => {
                icon.classList.remove('save-animation');
            }, 300);

            // Get saved courses from localStorage
            let savedCourses = JSON.parse(localStorage.getItem('savedCourses')) || [];
            
            // Check if course is already saved
            const courseIndex = savedCourses.indexOf(courseId);
            
            // Toggle save state in localStorage
            if (courseIndex === -1) {
                // Course not saved, add it
                savedCourses.push(courseId);
                console.log(`Course ${courseId} saved`);
            } else {
                // Course already saved, remove it
                savedCourses.splice(courseIndex, 1);
                console.log(`Course ${courseId} removed from saved`);
            }
            
            // Save updated list back to localStorage
            localStorage.setItem('savedCourses', JSON.stringify(savedCourses));
        }

        // Load saved courses from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const savedCourses = JSON.parse(localStorage.getItem('savedCourses')) || [];
            savedCourses.forEach(courseId => {
                const button = document.querySelector(`button[onclick="toggleSave(${courseId})"]`);
                if (button) {
                    const icon = button.querySelector('svg');
                    icon.classList.add('text-primary', 'fill-current');
                }
            });
        });
    </script>
</body>
</html>