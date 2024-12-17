<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//credentails for the vault
$vUser = $_ENV['VUSER'];
$vPass = $_ENV['VPASS'];
$vLoc = $_ENV['VLOC'];
$vdata = $_ENV['VDATA'];

$dbc = mysqli_connect($vLoc, $vUser, $vPass, $vdata) or die('not connecting');

// Execute the SQL query
$sql = "SELECT Jobcode, Description FROM jobcodes WHERE Tier IN (1,2)";
$result = mysqli_query($dbc, $sql);

// Fetch the job codes and store them in an array
$jobCodesArray = array();
while ($row = mysqli_fetch_assoc($result)) {
	$jobCodesArray[] = $row['Jobcode'];
	$jobCodesDescriptionArray[$row['Jobcode']] = $row['Description'];
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
				$logDetails = "Employee: $f_name $l_name at location: $worksite with Employee ID: $ifasid was found with a qualifying Job Code: $hitcodes with Job Description: $jobCodesDescriptionArray[$hitcodes], but has no devices assigned to them in the vault";
				$mail = new PHPMailer(true);
				try {
					// SMTP configuration
					$mail->isSMTP();
					$mail->Host = $_ENV['MAILHOST']; // Replace with your SMTP relay host
					$mail->Port = 25; // Replace with the appropriate port number
					$mail->SMTPAuth = false; // Set to true if your SMTP host requires authentication

					// Email content
					$mail->setFrom($_ENV['SENDEREMAIL'], 'teacher-no-device container');
					$mail->addAddress($_ENV['RECIPIENTEMAIL'], 'Teacher with no Device Report');
					$mail->Subject = 'Qualifying Teacher Job Code without Device Found';
					$mail->Body = $logDetails;

					// Send the email
					$mail->send();
					echo 'Email sent successfully.';
				} catch (Exception $e) {
					echo 'Failed to send the email. Error: ' . $mail->ErrorInfo;
				}

				// Log the email sent
				$insertLogSql = "INSERT INTO wo_email_log (ifasid, details) VALUES ('$ifasid', '$logDetails')";
				$dbc->query($insertLogSql);
			} else {
				// Email already sent for this teacher
			}
		}
	}
}
echo 'Script executed successfully.';
// Close the database connection
$dbc->close();
