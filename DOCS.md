Terminology
===
Several special terms are used in this plugin.

The network is considered as a human's circulatory **system**. Each server is considered as a **tissue**. As there are different servers in the network that serve the same function, e.g. survival, they are considered to be of the same **organ**. When a tissue or an external stimulation wants to send some signal to the **system**, it releases some **hormones**, which are actually rows in the `blood` table in the MySQL database. Each **organ** is assigned with an ID, which is defined at the `organs` table in the MySQL database. The status of each *tissue** (server) is loaded in the `tissues` table in the MySQL database.
