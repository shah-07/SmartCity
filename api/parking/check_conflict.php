<!DOCTYPE html>
<html>
<head><title>Test Conflict Check</title></head>
<body>
    <h1>Test Conflict Check</h1>
    <button onclick="testConflict()">Test Conflict Check</button>
    <pre id="result"></pre>
    
    <script>
        async function testConflict() {
            const data = {
                spotID: 1,
                startTime: '2026-01-02T10:00',
                endTime: '2026-01-02T11:00'
            };
            
            const response = await fetch('check_conflict.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            document.getElementById('result').textContent = JSON.stringify(result, null, 2);
        }
    </script>
</body>
</html>