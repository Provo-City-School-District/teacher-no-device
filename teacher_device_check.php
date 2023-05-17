<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
//credentails for the vault
$vUser = $_ENV['VUSER'];
$vPass = $_ENV['VPASS'];
$vLoc = $_ENV['VLOC'];
$vdata = $_ENV['VDATA'];


$dbc = mysqli_connect ($vLoc, $vUser, $vPass,$vdata) or die('not connecting');
// if ($dbc->connect_error) {
//     die('Connect Error (' . $dbc->connect_errno . ') '. $dbc->connect_error);
// }
// echo $dbc->host_info;




// Execute the SQL query
$sql = "SELECT Jobcode FROM jobcodes WHERE Tier IN (1,2)";
$result = mysqli_query($dbc, $sql);

// Fetch the job codes and store them in an array
$jobCodesArray = array();
while ($row = mysqli_fetch_assoc($result)) {
    $jobCodesArray[] = $row['Jobcode'];
}
 
 
 // Check if the wo_email_log table exists, if not, create it
 $tableExistsSql = "SHOW TABLES LIKE 'wo_email_log'";
 $tableExistsResult = $dbc->query($tableExistsSql);
 
 if ($tableExistsResult->num_rows == 0) {
 	$createTableSql = "CREATE TABLE wo_email_log (
 	id INT AUTO_INCREMENT PRIMARY KEY,
 	ifasid VARCHAR(10) NOT NULL,
 	details VARCHAR(255) NOT NULL,
 	sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 )";
 
 	$dbc->query($createTableSql);
 }
 
 // Fetch staff with HRstatus = A and join with staff_temp on ifasid
 $sql = "SELECT s.* FROM staff_temp s JOIN staff st ON s.ifasid = st.ifasid WHERE s.hr_status = 'A'";
 $result = $dbc->query($sql);
 
 if ($result->num_rows > 0) {
 	while ($row = $result->fetch_assoc()) {
 		$ifasid = $row['ifasid'];
 		$f_name = strtolower($row['firstname']);
 		$l_name = strtolower($row['lastname']);
		$worksite = strtolower($row['worksite']);
 
 		// Check if the teacher has a job code from the given list
 		$jobCodes = explode(';', $row['JOB_CODES']);
 		$matchingJobCodes = array_intersect($jobCodes, $jobCodesArray);
 
 		// Check if the teacher has a checked out device in the device_manager table
 		$sql = "SELECT * FROM assets WHERE ifas = '$ifasid'";
 		$deviceResult = $dbc->query($sql);
 
 		if ($deviceResult->num_rows == 0 && $matchingJobCodes) {
 			// Check if an email has already been sent for this teacher
 			$emailLogSql = "SELECT * FROM wo_email_log WHERE ifasid = '$ifasid'";
 			$emailLogResult = $dbc->query($emailLogSql);
 
 			if ($emailLogResult->num_rows == 0) {
 				$hitcodes = implode(',', $matchingJobCodes);
				$logDetails = "Employee: $f_name $l_name at location: $worksite with ifasid: $ifasid was found with a qualifying job code $hitcodes, but has no devices assigned to them in the vault";
				$mail = new PHPMailer(true);
				try {
					// SMTP configuration
					$mail->isSMTP();
					$mail->Host = 'smtp.provo.edu'; // Replace with your SMTP relay host
					$mail->Port = 25; // Replace with the appropriate port number
					$mail->SMTPAuth = false; // Set to true if SMTP authentication is required
					// $mail->Username = 'your_username'; // Uncomment and provide username if required
					// $mail->Password = 'your_password'; // Uncomment and provide password if required
				
					// Email content
					$mail->setFrom('donotreply@provo.edu', 'teacher-no-device container');
					$mail->addAddress('helpdesk.provo.edu', 'Teacher with no Device Report');
					$mail->Subject = 'New Teacher with no Device Found';
					$mail->Body = $logDetails;
				
					// Send the email
					$mail->send();
					echo 'Email sent successfully.';
				} catch (Exception $e) {
					echo 'Failed to send the email. Error: ' . $mail->ErrorInfo;
				}



 				// Log the email sent
 				// $logDetails = "Email sent for teacher Job Code attached to $f_name $l_name with ifasid: $ifasid";
 				$insertLogSql = "INSERT INTO wo_email_log (ifasid, details) VALUES ('$ifasid', '$logDetails')";
 				$dbc->query($insertLogSql);
 			} else {
 				// Email already sent for this teacher, handle accordingly
 				// ...
 			}
 		}
 	}
 }
 
 // Close the database connection
 $dbc->close();
 ?>