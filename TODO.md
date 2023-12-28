# TODO

- Clicking on ip address should search for that IP, rather than run whois
- Reset heatmap when search is reset, or just have heatmap generated whenever log is polled
- Have heatmap.php return entries for entire data range, with zeros if no data exists given the search term and exclusions
- Search interface should specify fields as parameter
- Multiple field searches should AND together
- Allow heatmap.php to filter with search term (which would happen automatically if the above were implemented)
- Exclusion function should move from hardwired list to loading from external JSON file or CSV
- Unified function to handle searches and paging, called by heatmap and front-end, that returns JSON array?
- External PHP function to create table header array? May not be neccesary if refactor puts this info in only one unified table generation function.
- Separate link next to IP for running whois on IP address
