function submitScan($request)
{
    $filename = $request['filename'];
    $filedata = base64_decode($request['filedata']);
    $description = $request['description'];
    $username = $request['username'];

    // Save file locally or to a scan folder
    $savePath = "/var/scans/" . time() . "_" . $filename;
    file_put_contents($savePath, $filedata);

    // Store metadata in DB
    $conn = new mysqli("localhost", "user", "pass", "scanDB");
    $stmt = $conn->prepare("INSERT INTO scans (username, filename, filepath, description, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $username, $filename, $savePath, $description);
    $stmt->execute();

    return array("status" => "success", "message" => "Scan submitted and saved.");
}
