FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV container docker

# Install systemd and essential networking tools
RUN apt-get update && \
    apt-get install -y systemd systemd-sysv sudo iputils-ping curl wget nano iptables iproute2 kmod tzdata \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
    
# Delete systemd targets that prevent running cleanly in a container
RUN cd /lib/systemd/system/sysinit.target.wants/ || exit; \
    for i in *; do [ $i = systemd-tmpfiles-setup.service ] || rm -f $i; done; \
    rm -f /lib/systemd/system/multi-user.target.wants/*; \
    rm -f /etc/systemd/system/*.wants/*; \
    rm -f /lib/systemd/system/local-fs.target.wants/*; \
    rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
    rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
    rm -f /lib/systemd/system/basic.target.wants/*; \
    rm -f /lib/systemd/system/anaconda.target.wants/*;

VOLUME [ "/sys/fs/cgroup" ]

# Wait command that allows systemd to take over PID 1
CMD ["/lib/systemd/systemd"]
