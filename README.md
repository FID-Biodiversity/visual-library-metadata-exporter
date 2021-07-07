# Visual Library Metadata Exporter

A PHP-Script to export metadata from the Visual Library Web API.

Author: V. Teuschler

The script allows reading metadata from the Visual Library Web API of the University Library Frankfurt. However, the API-URL can easily be changes.

The script assumes that there is already a folder containing PDFs with the item identifiers to look up. But also this can easily be changed to read e.g. a list of identifiers.

Finally, the collected data is written to a MySQL database that has to be running. The credentials can be set in the script.
