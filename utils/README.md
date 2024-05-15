# LogPager Utils

These scripts are provided for convenience and are meant to be run on the host, not within the container. They will setup and maintain a ufw firewall rule using cron.

## Warning

These scripts are still in development and may not be suitable for production use. Use at your own risk. In particular, the use of the UFW scripts may not work as expected when there are other firewall rules in use, most notably when using Docker, as Docker uses iptables directly such that user-added rules in UFW are usually superceded and thus ignored.

## Prerequisites

Before running these scripts, make sure you have the following prerequisites installed:

-   ufw (Uncomplicated Firewall)
-   cron (cron daemon)

## Usage

1. Clone this repository to your desired location on the host machine.

2. Navigate to the `logpager/utils` directory.

3. Run the `setup_firewall.sh` script to set up the ufw firewall rule:

    ```bash
    ./setup_firewall.sh
    ```

    This script will configure the firewall to allow incoming connections on the specified port.

4. To maintain the firewall rule and automatically block IPs from a blacklist, you can set up a cron job to run the `ufw_update.sh` script periodically using the `install_blacklist_cron.sh` script.

    ```bash
    ./install_blacklist_cron.sh /path/to/ufw_update.sh /path/to/blacklist.csv
    ```

    This script will set up a cron job to run the `ufw_update.sh` script every 5 minutes, passing the path to the blacklist file as a command-line argument. The `ufw_update.sh` script will update the firewall rules to block the IPs listed in the blacklist file.

## Contributing

If you encounter any issues or have suggestions for improvements, feel free to open an issue or submit a pull request on the [GitHub repository](https://github.com/your-username/logpager-utils).
