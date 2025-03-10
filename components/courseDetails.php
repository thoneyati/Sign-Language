<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "sign_language";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get course and lesson IDs from URL parameters
$course_id = isset($_GET['course']) ? intval($_GET['course']) : 0;
$lesson_id = isset($_GET['lesson']) ? intval($_GET['lesson']) : 0;

// Fetch course information
$course_stmt = $conn->prepare("SELECT title, description, thumbnail_url FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$course = $course_result->fetch_assoc();
$course_stmt->close();

if (!$course) {
    $course = ['title' => 'Course Not Found', 'description' => 'The requested course could not be found.'];
}

// Fetch current lesson information if a lesson ID is provided
$current_lesson = null;
if ($lesson_id > 0) {
    $lesson_stmt = $conn->prepare("SELECT id, title, description, category, created_by, created_at, course_id, rating, video_url FROM lesson WHERE id = ?");
    $lesson_stmt->bind_param("i", $lesson_id);
    $lesson_stmt->execute();
    $lesson_result = $lesson_stmt->get_result();
    $current_lesson = $lesson_result->fetch_assoc();
    $lesson_stmt->close();
}

// Fetch all lessons for this course
$lessons_stmt = $conn->prepare("SELECT id, title, description, created_at FROM lesson WHERE course_id = ? ORDER BY id ASC");
$lessons_stmt->bind_param("i", $course_id);
$lessons_stmt->execute();
$lessons_result = $lessons_stmt->get_result();
$lessons = $lessons_result->fetch_all(MYSQLI_ASSOC);
$lessons_stmt->close();

// Fetch comments for the current lesson
$comments = [];
if ($lesson_id > 0) {
    $comments_stmt = $conn->prepare("
        SELECT lc.id, lc.comment, lc.created_at, u.username, u.profile 
        FROM lesson_comments lc
        JOIN users u ON lc.user_id = u.id
        WHERE lc.lesson_id = ?
        ORDER BY lc.created_at DESC
    ");

    // If the join with users table fails (e.g., if the users table has a different structure),
    // use this simpler query instead:
    /*
    $comments_stmt = $conn->prepare("
        SELECT id, user_id, comment, created_at
        FROM lesson_comments
        WHERE lesson_id = ? AND parent_comment_id IS NULL
        ORDER BY created_at DESC
    ");
    */

    $comments_stmt->bind_param("i", $lesson_id);
    $comments_stmt->execute();
    $comments_result = $comments_stmt->get_result();
    $comments = $comments_result->fetch_all(MYSQLI_ASSOC);
    $comments_stmt->close();
}

$user_id = $_SESSION['user']['user_id']; // Assuming user is logged in and session is set
$course_id = $_GET['course']; // Course ID from URL or request

if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(16)); // Unique token
}

$user_id = $_SESSION['user']['user_id'] ?? null;
$course_id = $_GET['course'] ?? null;

// Check enrollment status
$query = "SELECT status FROM course_enrollments WHERE user_id = ? AND course_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();
$enrollment = $result->fetch_assoc();
$stmt->close();

$enrollment_status = $enrollment ? $enrollment['status'] : null;

// Handle form submission


// Close the database connection
$conn->close();

// Helper function to format dates
function formatTimeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $time);
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(string: $course['title'] ?? 'Course View'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="bg-gray-50">
    <!-- Main Content -->
    <div class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column (2/3 width on large screens) -->
            <div class="lg:col-span-2">
                <div class="container mx-auto py-8">
                    <?php if ($enrollment_status === "approved"): ?>
                        <!-- Approved Enrollment: Show Course Content -->
                        <div id="courseContent" class="bg-white rounded-lg shadow-md p-6">
                            <?php if ($current_lesson): ?>
                                <div class="aspect-video">
                                    <?php if (!empty($current_lesson['video_url'])): ?>
                                        <video controls class="w-full h-full rounded-md" id="lessonVideo" preload="metadata">
                                            <source src="<?php echo htmlspecialchars($current_lesson['video_url']); ?>"
                                                type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php else: ?>
                                        <div class="flex items-center justify-center h-full bg-gray-200 text-gray-600 rounded-md">
                                            <p>No video available for this lesson</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-4">
                                    <h2 class="text-2xl font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($current_lesson['title']); ?></h2>
                                    <p class="text-gray-600 mt-2">
                                        <?php echo htmlspecialchars($current_lesson['description']); ?></p>
                                    <div class="mt-4 flex justify-between items-center">
                                        <span class="text-sm text-gray-500">Category:
                                            <?php echo htmlspecialchars($current_lesson['category'] ?? 'Uncategorized'); ?></span>
                                        <div class="space-x-3">
                                            <button onclick="openQuiz(<?php echo $lesson_id; ?>)"
                                                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Take
                                                Quiz</button>
                                            <button onclick="navigateToNextLesson(<?php echo $current_lesson['id']; ?>)"
                                                class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300">Next
                                                Lesson</button>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <p class="text-gray-600">No lesson content available yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($enrollment_status === 'pending'): ?>
                        <!-- Pending Enrollment: Show Waiting Message -->
                        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 text-center">
                            <svg class="mx-auto h-12 w-12 text-yellow-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-lg font-medium text-gray-900">Enrollment Pending</h3>
                            <p class="mt-1 text-sm text-gray-500">Your enrollment is under review. Please check back later.
                            </p>
                        </div>

                    <?php else: ?>
                        <!-- Not Enrolled: Show Enrollment Option -->
                        <div class="max-w-md mx-auto rounded-lg shadow-md border border-gray-200 overflow-hidden">
                            <div class="h-48 bg-cover bg-center"
                                style="background-image: url('<?php echo $course['thumbnail_url']; ?>');">
                                <div class="h-full bg-gray-900 bg-opacity-50 flex items-center justify-center">
                                    <form method="POST" id="enrollForm" action="handlers/enroll.php">
                                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                        <input type="hidden" name="course_id" value="<?= $course_id ?>">
                                        <button type="submit" id="enrollBtn" class="bg-white text-gray-900 px-6 py-3 rounded-md font-medium
                                    hover:bg-gray-100 hover:text-blue-800 transition-all duration-200 shadow-md">
                                            Enroll Now
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-5">
                    <?php include 'components/comments.php'; ?>

                </div>
            </div>

            <!-- Right Column (1/3 width on large screens) -->
            <!-- <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold mb-4">Course Lessons</h3>
                    <div class="space-y-3">
                        <?php if (empty($lessons)): ?>
                            <p class="text-gray-500">No lessons available for this course.</p>
                        <?php else: ?>
                            <?php foreach ($lessons as $lesson): ?>
                                <a
                                    href="?course=<?php echo $course_id; ?>&lesson=<?php echo $lesson['id']; ?>"
                                    class="block p-3 rounded-md hover:bg-gray-50 <?php echo ($lesson_id == $lesson['id']) ? 'bg-blue-50 border-l-4 border-[#4A90E2]' : ''; ?>">
                                    <h4 class="font-medium"><?php echo htmlspecialchars($lesson['title']); ?></h4>
                                    <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars(substr($lesson['description'], 0, 60) . (strlen($lesson['description']) > 60 ? '...' : '')); ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div> -->

            <div class="lg:col-span-1">
                <!-- Course Title -->
                <div class="bg-white p-6 rounded-t-lg shadow-sm border border-gray-100">
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($course['description']); ?></p>
                </div>
                <div class="bg-white p-6 rounded-b-lg shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-semibold">Course Lessons</h3>
                        <div class="text-sm text-gray-500">
                            <span class="font-medium text-indigo-600"><?php echo count(array_filter($lessons, function ($l) {
                                return isset($l['completed']) && $l['completed'];
                            })); ?></span>
                            <span>/</span>
                            <span><?php echo count($lessons); ?></span>
                            <span class="ml-1">completed</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <?php if (empty($lessons)): ?>
                            <div class="py-8 flex flex-col items-center justify-center text-center">
                                <div class="bg-gray-100 p-3 rounded-full mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                </div>
                                <p class="text-gray-500">No lessons available for this course.</p>
                                <button class="mt-3 text-sm text-indigo-600 hover:text-indigo-800 font-medium">Refresh
                                    List</button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($lessons as $index => $lesson): ?>
                                <?php
                                $isActive = ($lesson_id == $lesson['id']);
                                $isCompleted = isset($lesson['completed']) && $lesson['completed'];
                                $lessonNumber = $index + 1;
                                ?>
                                <a href="?course=<?php echo $course_id; ?>&lesson=<?php echo $lesson['id']; ?>" class="flex items-start gap-3 border  p-3 rounded-lg transition-all duration-200 relative
                            <?php echo $isActive
                                ? 'bg-indigo-50 border-indigo-100'
                                : 'hover:bg-gray-50  border-transparent hover:border-gray-100'; ?>">

                                    <!-- Lesson number or status indicator -->
                                    <!-- <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-sm font-medium
                            <?php if ($isCompleted): ?>
                                bg-green-100 text-green-700
                            <?php elseif ($isActive): ?>
                                bg-indigo-100 text-indigo-700
                            <?php else: ?>
                                bg-gray-100 text-gray-700
                            <?php endif; ?>">
                            <?php if ($isCompleted): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            <?php else: ?>
                                <?php echo $lessonNumber; ?>
                            <?php endif; ?>
                        </div> -->

                                    <div id="videoContainer"
                                        class="relative h-28 aspect-square bg-gray-300 rounded-lg flex items-center justify-center cursor-pointer group overflow-hidden">
                                        <!-- Play Button -->
                                        <div class=" size-8 bg-gray-500 rounded-full flex items-center justify-center">
                                            <i class="fa-solid fa-play text-white"></i>
                                        </div>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between">
                                            <h4 class="font-medium text-gray-900 line-clamp-1">
                                                <?php echo htmlspecialchars($lesson['title']); ?>
                                            </h4>

                                            <?php if (isset($lesson['duration'])): ?>
                                                <span class="text-xs text-gray-500 flex items-center ml-2 flex-shrink-0">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <?php echo $lesson['duration']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="text-sm text-gray-500 line-clamp-2 mt-0.5">
                                            <?php echo htmlspecialchars($lesson['description']); ?>
                                        </p>

                                        <?php if ($isActive && !$isCompleted): ?>
                                            <span class="inline-flex items-center mt-2 text-xs font-medium text-indigo-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Continue Learning
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($isActive): ?>
                                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500 rounded-l-lg"></div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>


                </div>
            </div>
        </div>
    </div>

    <!-- Chat Button -->
    <div class="fixed bottom-8 right-8">
        <button class="bg-[#4A90E2] text-white p-4 rounded-full shadow-lg hover:bg-[#357abd]">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
                </path>
            </svg>
        </button>
    </div>

    <!-- Quiz Modal/Popup -->
    <div id="quizModal"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden overflow-y-auto modal-fade-in flex items-start justify-center pt-10 pb-10">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-4 my-8 modal-slide-up relative">
            <!-- Modal Header -->
            <div
                class="bg-gradient-to-r from-primary-600 to-primary-700 text-slate-900 rounded-t-xl px-6 py-4 flex justify-between items-center">
                <h2 id="modalLessonTitle" class="text-xl font-bold">Lesson Quizzes</h2>
                <button onclick="closeModal()"
                    class="text-slate-900 hover:text-primary-200 transition-colors focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Content -->
            <div id="quizContent" class="p-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <!-- Quiz content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // DOM elements
        const courseContent = document.getElementById('courseContent');
        const loadingState = document.getElementById('loadingState');
        const errorState = document.getElementById('errorState');
        const errorMessage = document.getElementById('errorMessage');
        const errorDetails = document.getElementById('errorDetails');
        const commentForm = document.getElementById('commentForm');
        const commentText = document.getElementById('commentText');
        const commentsList = document.getElementById('commentsList');

        // Add video error handling
        const video = document.getElementById('lessonVideo');
        const videoError = document.getElementById('videoError');

        if (video) {
            video.addEventListener('error', (e) => {
                console.error('Video Error:', e);
                if (videoError) {
                    videoError.classList.remove('hidden');
                }
            });
        }

        // Handle comment form submission
        if (commentForm) {
            commentForm.addEventListener('submit', function (e) {
                e.preventDefault();

                if (!commentText.value.trim()) {
                    alert('Please enter a comment');
                    return;
                }

                const lessonId = <?php echo $lesson_id ?: 0; ?>;

                if (!lessonId) {
                    alert('No lesson selected');
                    return;
                }

                // In a real application, you would send this to the server
                // For now, we'll just add it to the UI
                const now = new Date();
                const comment = {
                    username: 'You',
                    comment: commentText.value,
                    created_at: now.toISOString()
                };

                // Add the new comment to the top of the list
                const commentElement = document.createElement('div');
                commentElement.className = 'border-b pb-4 mb-4';
                commentElement.innerHTML = `
                <div class="flex items-start space-x-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-700 font-semibold">
                        Y
                    </div>
                    <div>
                        <p class="font-medium">${comment.username}</p>
                        <p class="text-gray-600 mb-2">${comment.comment}</p>
                        <p class="text-sm text-gray-500">just now</p>
                    </div>
                </div>
            `;

                // If there's a "no comments" message, remove it
                const noCommentsMessage = commentsList.querySelector('.text-center');
                if (noCommentsMessage) {
                    noCommentsMessage.remove();
                }

                // Add the new comment at the top
                commentsList.insertBefore(commentElement, commentsList.firstChild);

                // Clear the form
                commentText.value = '';

                // In a real application, you would send an AJAX request like this:
                /*
                fetch('/handlers/addComment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lesson_id: lessonId,
                        comment: comment.comment
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to post comment. Please try again.');
                });
                */
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const userId = localStorage.getItem('id');
            if (userId) {
                document.getElementById('userId').value = userId;
            } else {
                alert('User ID not found in local storage.');
            }
        });
        // Navigate to next lesson
        function navigateToNextLesson(currentLessonId) {
            // Get all lesson links
            const lessonLinks = document.querySelectorAll('a[href^="?course="]');
            let nextLessonLink = null;
            let foundCurrent = false;

            // Find the current lesson and get the next one
            for (const link of lessonLinks) {
                if (foundCurrent) {
                    nextLessonLink = link;
                    break;
                }

                if (link.href.includes(`lesson=${currentLessonId}`)) {
                    foundCurrent = true;
                }
            }

            // Navigate to the next lesson if found
            if (nextLessonLink) {
                window.location.href = nextLessonLink.href;
            } else {
                alert('This is the last lesson in the course.');
            }
        }

        // function OpenQuizz(currentLessonId) {
        //     // Ensure a valid lesson ID
        //     if (!currentLessonId) {
        //         alert("No lesson selected!");
        //         return;
        //     }

        //     // Fetch quiz data using AJAX
        //     fetch(`handlers/get_quizzes.php?lesson_id=${currentLessonId}`)
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.length === 0) {
        //                 document.getElementById('quizContent').innerHTML = "<p class='text-gray-500'>No quizzes available for this lesson.</p>";
        //             } else {
        //                 let quizHtml = "";
        //                 data.forEach((quiz, index) => {
        //                     quizHtml += `
        //                         <div class="border p-4 rounded-lg mb-4 bg-gray-50 shadow-md">
        //                             <p class="font-semibold text-lg text-gray-800">${index + 1}. ${quiz.question}</p>
        //                             <div class= " flex mt-3 space-y-2">
        //                                 <label class="flex items-center space-x-3 bg-white p-2 rounded-md shadow-sm hover:bg-gray-100 cursor-pointer">
        //                                     <input type="radio" name="quiz_${quiz.id}" value="A" class="accent-green-500">
        //                                     <img src="${quiz.option_a}" class="h-12 w-auto rounded-md border">
        //                                 </label>
        //                                 <label class="flex items-center space-x-3 bg-white p-2 rounded-md shadow-sm hover:bg-gray-100 cursor-pointer">
        //                                     <input type="radio" name="quiz_${quiz.id}" value="B" class="accent-green-500">
        //                                     <img src="${quiz.option_b}" class="h-12 w-auto rounded-md border">
        //                                 </label>
        //                                 <label class="flex items-center space-x-3 bg-white p-2 rounded-md shadow-sm hover:bg-gray-100 cursor-pointer">
        //                                     <input type="radio" name="quiz_${quiz.id}" value="C" class="accent-green-500">
        //                                     <img src="${quiz.option_c}" class="h-12 w-auto rounded-md border">
        //                                 </label>
        //                             </div>
        //                         </div>`;

        //                 });

        //                 document.getElementById('quizContent').innerHTML = quizHtml;
        //             }

        //             // Show the modal
        //             document.getElementById('quizModal').classList.remove('hidden');
        //         })
        //         .catch(error => console.error("Error fetching quizzes:", error));
        // }

        // function closeModal() {
        //     document.getElementById("quizModal").classList.add("hidden"); 
        // }

        function openQuiz(currentLessonId) {
            // Ensure a valid lesson ID
            if (!currentLessonId) {
                alert("No lesson selected!")
                return
            }

            // Fetch quiz data using AJAX
            fetch(`handlers/get_quizzes.php?lesson_id=${currentLessonId}`)
                .then((response) => response.json())
                .then((data) => {
                    if (data.length === 0) {
                        document.getElementById("quizContent").innerHTML =
                            "<p class='text-gray-500'>No quizzes available for this lesson.</p>"
                    } else {
                        let quizHtml = `<form id="quizForm" data-lesson-id="${currentLessonId}">`
                        data.forEach((quiz, index) => {
                            quizHtml += `
                        <div class="border p-4 rounded-lg mb-4 bg-gray-50 shadow-md">
                            <p class="font-semibold text-lg text-gray-800">${index + 1}. ${quiz.question}</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                                <label class="flex items-center space-x-3 bg-white p-2 rounded-md shadow-sm hover:bg-gray-100 cursor-pointer">
                                    <input type="radio" name="quiz_${quiz.id}" value="A" class="accent-green-500" required>
                                    <img src="${quiz.option_a}" class="h-12 w-auto rounded-md border">
                                </label>
                                <label class="flex items-center space-x-3 bg-white p-2 rounded-md shadow-sm hover:bg-gray-100 cursor-pointer">
                                    <input type="radio" name="quiz_${quiz.id}" value="B" class="accent-green-500">
                                    <img src="${quiz.option_b}" class="h-12 w-auto rounded-md border">
                                </label>
                                <label class="flex items-center space-x-3 bg-white p-2 rounded-md shadow-sm hover:bg-gray-100 cursor-pointer">
                                    <input type="radio" name="quiz_${quiz.id}" value="C" class="accent-green-500">
                                    <img src="${quiz.option_c}" class="h-12 w-auto rounded-md border">
                                </label>
                            </div>
                        </div>`
                        })

                        quizHtml += `
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-gray-600 hover:bg-primary-700 text-white font-bold py-2 px-6 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                            Submit Answers
                        </button>
                    </div>
                </form>`

                        document.getElementById("quizContent").innerHTML = quizHtml

                        // Add event listener for form submission
                        document.getElementById("quizForm").addEventListener("submit", submitQuiz)
                    }

                    // Show the modal
                    document.getElementById("quizModal").classList.remove("hidden")
                })
                .catch((error) => console.error("Error fetching quizzes:", error))
        }

        function submitQuiz(event) {
            event.preventDefault()

            const form = event.target
            const lessonId = form.dataset.lessonId
            const answers = {}

            // Collect all answers
            const radioButtons = form.querySelectorAll('input[type="radio"]:checked')
            radioButtons.forEach((radio) => {
                const quizId = radio.name.replace("quiz_", "")
                answers[quizId] = radio.value
            })

            // Check if all questions are answered
            const totalQuestions = document.querySelectorAll(".border.p-4.rounded-lg").length
            if (Object.keys(answers).length < totalQuestions) {
                alert("Please answer all questions before submitting.")
                return
            }

            // Submit answers to server
            const userId = localStorage.getItem('id');

            fetch("handlers/submit_quiz.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    userId: userId,
                    answers: answers,
                }),
            })
                .then((response) => response.json())
                .then((results) => {
                    showResults(results)
                })
                .catch((error) => {
                    console.error("Error submitting quiz:", error)
                    alert("There was an error submitting your quiz. Please try again.")
                })
        }

        function showResults(results) {
            // Create results HTML
            const resultsHtml = `
        <div class="bg-white rounded-xl shadow-lg p-6 max-w-2xl mx-auto">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full ${results.percentage >= 70 ? "bg-green-100" : "bg-amber-100"} mb-4">
                    <span class="text-2xl font-bold ${results.percentage >= 70 ? "text-green-600" : "text-amber-600"}">${results.percentage}%</span>
                </div>
                <h3 class="text-xl font-bold">${results.percentage >= 70 ? "Congratulations!" : "Good effort!"}</h3>
                <p class="text-gray-600">You got ${results.correct} out of ${results.total} questions correct.</p>
            </div>
            
            <div class="space-y-4 mt-6">
                <h4 class="font-semibold text-lg border-b pb-2">Question Review</h4>
                ${results.questions
                    .map(
                        (q, index) => `
                    <div class="p-3 rounded-lg ${q.is_correct ? "bg-green-50 border border-green-200" : "bg-red-50 border border-red-200"}">
                        <p class="font-medium">${index + 1}. ${q.question}</p>
                        <div class="mt-2 text-sm">
                            <p>Your answer: <span class="font-semibold">${q.selected}</span></p>
                            ${!q.is_correct ? `<p>Correct answer: <span class="font-semibold text-green-600">${q.correct}</span></p>` : ""}
                        </div>
                    </div>
                `,
                    )
                    .join("")}
            </div>
            
            <div class="mt-6 flex justify-center">
                <button onclick="closeResultsModal()" class="bg-primary-600 hover:bg-primary-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                    Close
                </button>
            </div>
        </div>
    `

            // Create and show results modal
            const resultsModal = document.createElement("div")
            resultsModal.id = "resultsModal"
            resultsModal.className =
                "fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 modal-fade-in"
            resultsModal.innerHTML = resultsHtml

            document.body.appendChild(resultsModal)

            // Hide the quiz modal
            document.getElementById("quizModal").classList.add("hidden")
        }

        function closeResultsModal() {
            const resultsModal = document.getElementById("resultsModal")
            if (resultsModal) {
                resultsModal.remove()
            }
        }

        function closeModal() {
            document.getElementById("quizModal").classList.add("hidden")
        }



        // Mark lesson as complete
        function markLessonComplete(lessonId) {
            // In a real application, you would send an AJAX request to mark the lesson as complete
            alert(`Lesson ${lessonId} marked as complete!`);

            // Then navigate to the next lesson
            navigateToNextLesson(lessonId);
        }

    </script>
</body>

</html>