_:

{
  fantasyphp.name = "Sleeper";

  # .env is present and holds the IDs the example scripts run against, but
  # devenv's own dotenv integration needs the C-Nix CLI and is unavailable
  # through the flake, so examples/ids.php loads the file itself.
  dotenv.disableHint = true;
}
