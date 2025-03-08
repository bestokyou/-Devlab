<?php
require_once 'config.php';
checkLogin();
?>
<!DOCTYPE html>
<html>

<head>
    <title>PHP Learning Materials - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
</head>

<body class="bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="fixed top-0 left-0 right-0 z-50">
            <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 rounded">
                <div class="flex items-center">
                    <a href="dashboard.php">
                        <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                    </a>
                    <h1 class="text-2xl font-bold">PHP Learning Materials</h1>
                </div>
                <div class="flex items-center gap-4">
                    <a href="dashboard_php_detail.php" class="text-blue-500 hover:underline"> Back to PHP Course</a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
                </div>
            </div>
        </div>
        <!-- Content Sections -->
        <div class="grid grid-cols-1 gap-6 mt-20">
            <!-- Basic Concepts -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">แนวคิด PHP ขั้นพื้นฐาน</h2>
                <div class="prose max-w-none">
                    <h3 class="text-xl font-semibold mb-3">PHP คืออะไร?</h3>
                    <p class="mb-4">PHP (PHP: Hypertext Preprocessor) เป็นภาษาการเขียนโปรแกรมที่ทำงานฝั่งเซิร์ฟเวอร์
                        ออกแบบมาเพื่อพัฒนาเว็บแอปพลิเคชันโดยเฉพาะ</p>
                    <h3 class="text-xl font-semibold mb-3">PHP Syntax พื้นฐาน</h3>
                    <div class="bg-gray-100 p-4 rounded-lg mb-4">
                        <pre class="text-sm">
&lt;?php
    // Your PHP code here
    echo "Hello, World!";
?&gt;
                        </pre>
                    </div>
                </div>
            </div>
            <!-- Variables and Data Types -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">ตัวแปรและชนิดข้อมูล</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">การประกาศตัวแปร</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
$name = "John";          // String
$age = 25;              // Integer
$height = 1.75;         // Float
$isStudent = true;      // Boolean
$colors = array("red", "blue"); // Array
                            </pre>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Strings Operations</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
$firstName = "John";
$lastName = "Doe";
// String concatenation
echo $firstName . " " . $lastName;
// String length
echo strlen($firstName);
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Control Structures -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Control Structures</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">If Statements</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
if ($age >= 18) {
    echo "You are an adult";
} else {
    echo "You are a minor";
}
                            </pre>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Loops</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
// For Loop
for ($i = 0; $i < 5; $i++) {
    echo $i;
}
// While Loop
while ($counter < 10) {
    echo $counter;
    $counter++;
}
// Foreach Loop
foreach ($colors as $color) {
    echo $color;
}
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Functions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Functions</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">การสร้างและเรียกใช้ฟังก์ชัน</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
// Function definition
function sayHello($name) {
    return "Hello, " . $name;
}
// Function call
echo sayHello("John");
// Function with default parameter
function greet($name = "Guest") {
    echo "Welcome, " . $name;
}
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Arrays -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Arrays</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Indexed Arrays</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
$fruits = array("Apple", "Banana", "Orange");
// or
$fruits = ["Apple", "Banana", "Orange"];
// Accessing elements
echo $fruits[0]; // outputs "Apple"
                            </pre>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Associative Arrays</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
$person = array(
    "name" => "John",
    "age" => 25,
    "city" => "New York"
);
// Accessing elements
echo $person["name"]; // outputs "John"
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Forms and POST/GET -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Forms and POST/GET</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Handling Form Data</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
// POST Method
if ($_POST["username"]) {
    echo "Welcome, " . $_POST["username"];
}
// GET Method
if (isset($_GET["id"])) {
    echo "ID: " . $_GET["id"];
}
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Database Operations -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Database Operations</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">MySQL Connection</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
// Database connection
$conn = mysqli_connect("localhost", "username", "password", "database");
// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// Select query
$sql = "SELECT * FROM users";
$result = mysqli_query($conn, $sql);
// Insert query
$sql = "INSERT INTO users (name, email) VALUES ('John', 'john@example.com')";
mysqli_query($conn, $sql);
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>