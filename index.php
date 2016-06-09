<?php
$config_file = "./offlineimap.config";

function read_config($config) {
	$output = array();
	$parsed_config = array();
	$group = "";

	$config = explode("\n", $config);
	foreach($config as $line) {
		$line = trim($line);
		if(!strlen($line))
			continue;
		if(preg_match("#^\[(.*)\]$#", $line, $match)) {
			// Gruppe
			$group = strtolower($match[1]);
		} else {
			// Eigenschaft
			$property = explode("=", $line, 2);
			$property = array_map("trim", $property);
			list($key, $value) = $property;
			switch($group) {
				case "general" :
					switch($key) {
						case "accounts" :
							$value = explode(",", $value);
							$value = array_map("trim", $value);
							break;
					}
					break;
				case "account" :
					switch($key) {
					}
					break;
				case "repository" :
					switch($key) {
					}
					break;
			}
			$parsed_config[$group][$key] = $value;
		}
	}

	foreach($parsed_config as $group => $properties) {
		if(strpos($group, " ") !== FALSE) {
			// Leerzeichen vorhanden
			$group = explode(" ", $group, 2);
			$group = array_map("trim", $group);
			if(!$output[$group[0]])
				$output[$group[0]] = array();
			$output[$group[0]][$group[1]] = $properties;
		} else {
			$output[$group] = $properties;
		}
	}

	return $output;
}

function write_config(array $config) {
	$parsed_config = array();

	foreach($config as $group => $properties) {
		switch(strtolower($group)) {
			case "general" :
				foreach($properties as $key => $value) {
					switch(strtolower($key)) {
						case "accounts" :
							$properties[$key] = implode(", ", $value);
							break;
					}
				}
				$parsed_config[$group] = $properties;
				break;
			case "account" :
				foreach($properties as $account => $account_properties) {
					$parsed_config[$group . " " . $account] = $account_properties;
				}
				break;
			case "repository" :
				foreach($properties as $account => $repository_properties) {
					$parsed_config[$group . " " . $account] = $repository_properties;
				}
				break;
		}
	}

	foreach($parsed_config as $group => $properties) {
		$group = strtolower($group);
		if($group != "general") {
			$group = ucfirst($group);
		}
		$output[] = "[" . $group . "]";
		foreach($properties as $key => $value)
			$output[] = $key . " = " . $value;
		$output[] = "\n";
	}
	$output = implode("\n", $output);

	return $output;
}

//echo $read_config;
//print_r(read_config($read_config));
//echo "\n\n\n";
//echo write_config(read_config($read_config));
if($_POST) {
	// Accounts umbenennen
	foreach($_POST["accounts"] as $old => $new) {
		if($old == $new)
			continue;
		$_POST["repository"][$new] = $_POST["repository"][$old];
		unset($_POST["repository"][$old]);

		// General (accounts) aktualisieren
		foreach($_POST["general"]["accounts"] as &$account) {
			if($account == $old)
				$account = $new;
		}
	}

	unset($_POST["accounts"]);

	// Repositories umbenennen
	foreach($_POST["repositories"] as $old => $new) {
		if($old == $new)
			continue;
		$_POST["repository"][$new] = $_POST["repository"][$old];
		unset($_POST["repository"][$old]);

		// Accounts aktualisieren
		foreach($_POST["account"] as $account => $properties) {
			foreach($properties as $key => &$value) {
				if($value == $old)
					$value = $new;
			}
		}
	}
	unset($_POST["repositories"]);

	file_put_contents($config_file, write_config($_POST));
}

$config = read_config(file_get_contents($config_file));
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>Imap-Sync-Konfigurationsgenerator</title>
		<style>
			html, body {
				font-family: Helvetica, Arial;
			}

			*  {
				font-family: Helvetica, Arial;
			}

			h3 input {
				font-size: 16px;
				border: 0 none;
				width: 100%;
				border-bottom: 1px solid black;
			}

			table.tree {
				width: 100%;
			}

			table.tree th {
				text-align: right;
				font-weight: bold;
				background-color: #EFF1F4;
				border: 1px solid white;
				padding: 3px 7px;
				width: 50%;
				line-height: 19px
			}

			table.tree td {
				width: 50%;
				text-align: left;
				padding: 3px 7px
			}

			table.tree input, table.tree textarea, table.tree select {
				border: 1px solid #D3DBEB;
				width: 100%
			}

			table.tree textarea {
				height: 180px;
				font-size: 12px
			}

			table.tree th .desc {
				font-weight: normal;
				font-size: 11px;
				line-height: 14px;
				font-style: italic;
				margin-top: 5px
			}

			div.block {
				width: 50%;
				float: left;
			}
		</style>
	</head>
	<body>
		<form method="post">
			<fieldset>
				<legend>
					Allgemeine Informationen [general]
				</legend>
				<table class="tree">
					<?php
					foreach($config["general"] as $key => $value) {
						$input = "";
						$desc = "";

						echo '<tr>';
						$input = '<input type="text" name="general[' . $key . ']" value="' . $value . '">';
						switch($key) {
							case "accounts" :
								$desc = "Liste der Accounts, die synchronisiert werden sollen.";
								$input = '<select name="general[' . $key . '][]" multiple size=2>';
								foreach(array_keys($config["account"]) as $account) {
									$input .= '<option value="' . $account . '"' . (in_array($account, $value) ? ' selected="selected"' : "") . '>' . $account . '</option>';
								}
								$input .= '</select>';
								break;
						}
						echo '<th>' . $key;
						if($desc) {
							echo "<br><small>" . $desc . "</small>";
						}
						echo '</th><td>';
						echo $input;
						echo '</td></tr>';
					}
					?>
				</table>
			</fieldset>
			<fieldset>
				<legend>
					Accounts [accounts]
				</legend>
				<?php
				foreach($config["account"] as $account => $account_properties) {
					echo '<div class="block">';
					echo '<h3><input type="text" name="accounts[' . $account . ']" value="' . $account . '"></h3>';
					echo '<table class="tree">';
					foreach($account_properties as $key => $value) {
						$input = "";
						$desc = "";

						echo '<tr>';
						$input .= '<input type="text" name="account[' . $account . '][' . $key . ']" value="' . $value . '">';
						switch($key) {
							case "localrepository" :
							case "remoterepository" :
								$desc = "remoterepository: Quelle der Synchronisation<br>localrepository: Ziel der Synchronisation";
								$input = '<select name="account[' . $account . '][' . $key . ']">';
								foreach(array_keys($config["repository"]) as $repository) {
									$input .= '<option value="' . $repository . '"' . (($repository == $value) ? ' selected="selected"' : "") . '>' . $repository . '</option>';
								}
								$input .= '</select>';
								break;
						}
						echo '<th>' . $key;
						if($desc) {
							echo "<br><small>" . $desc . "</small>";
						}
						echo '</th><td>';
						echo $input;
						echo '</td></tr>';
					}
					echo '</table>';
					echo '</div>';
				}
				?>
			</fieldset>
			<fieldset>
				<legend>
					Repositories [repositories]
				</legend>
				<?php
				foreach($config["repository"] as $repository => $repository_properties) {
					echo '<div class="block">';
					echo '<h3><input type="text" name="repositories[' . $repository . ']" value="' . $repository . '"></h3>';
					echo '<table class="tree">';
					foreach($repository_properties as $key => $value) {
						$input = "";
						$desc = "";

						echo '<tr>';
						$input .= '<input type="text" name="repository[' . $repository . '][' . $key . ']" value="' . $value . '">';
						switch($key) {
							case "realdelete" :
								$desc = "yes: E-Mails vom Server löschen<br>no: Nur Labels, etc. löschen, E-Mail behalten";
								break;
						}
						echo '<th>' . $key;
						if($desc) {
							echo "<br><small>" . $desc . "</small>";
						}
						echo '</th><td>';
						echo $input;
						echo '</td></tr>';
					}
					echo '</table>';
					echo '</div>';
				}
				?>
			</fieldset>
			<button type="submit">
				Änderungen speichern
			</button>
		</form>
	</body>
</html>
