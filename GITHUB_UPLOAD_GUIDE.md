# 📚 Gabay sa Pag-upload ng Project sa GitHub (Step-by-Step)

## 🎯 Overview
Ito ay komprehensibong gabay kung paano i-upload ang iyong Menstrual Tracking App sa GitHub.

---

## 📋 MGA HAKBANG

### **STEP 1: Gumawa ng GitHub Account**
1. Pumunta sa [github.com](https://github.com)
2. Click "Sign up" kung wala ka pang account
3. Sundin ang registration process
4. I-verify ang email address mo

---

### **STEP 2: Gumawa ng Bagong Repository sa GitHub**
1. Mag-login sa GitHub
2. Click ang **"+"** button sa upper right corner
3. Piliin **"New repository"**
4. Punuan ang mga sumusunod:
   - **Repository name**: `Menstrual` (o kahit anong gusto mong pangalan)
   - **Description**: "Menstrual Cycle Tracking Application" (optional)
   - **Visibility**: Piliin **Public** (libre) o **Private** (kung may bayad)
5. **HUWAG** i-check ang "Initialize this repository with a README"
6. Click **"Create repository"**

---

### **STEP 3: I-install ang Git (kung wala pa)**
1. I-download ang Git mula sa [git-scm.com](https://git-scm.com/download/win)
2. I-install ang Git installer
3. I-restart ang terminal/command prompt pagkatapos

**Para i-verify kung naka-install na:**
```bash
git --version
```

---

### **STEP 4: I-configure ang Git (Una lang ito)**
Buksan ang terminal/command prompt at i-type:

```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

**Halimbawa:**
```bash
git config --global user.name "Juan Dela Cruz"
git config --global user.email "juan@gmail.com"
```

---

### **STEP 5: I-initialize ang Git Repository sa Project Folder**
1. Buksan ang terminal/command prompt
2. Pumunta sa project folder:
   ```bash
   cd C:\xampp\htdocs\Menstrual
   ```
3. I-initialize ang Git:
   ```bash
   git init
   ```

---

### **STEP 6: I-add ang lahat ng Files**
```bash
git add .
```

**Note:** Ang `.gitignore` file ay naka-set up na para hindi ma-upload ang sensitive files tulad ng `config.php`

---

### **STEP 7: Gumawa ng Unang Commit**
```bash
git commit -m "Initial commit - Menstrual Tracking App"
```

---

### **STEP 8: I-connect ang Local Repository sa GitHub**
1. Balik sa GitHub repository page na ginawa mo sa Step 2
2. Kopyahin ang repository URL (makikita mo ito sa page, halimbawa: `https://github.com/username/Menstrual.git`)

3. Sa terminal, i-type:
   ```bash
   git remote add origin https://github.com/username/Menstrual.git
   ```
   *(Palitan ang `username` at `Menstrual` ng actual na username at repository name mo)*

---

### **STEP 9: I-upload ang Files sa GitHub**
```bash
git branch -M main
git push -u origin main
```

**Note:** Hihingin ang username at password mo:
- **Username**: GitHub username mo
- **Password**: Gumamit ng **Personal Access Token** (hindi ang regular password)

---

### **STEP 10: Gumawa ng Personal Access Token (Kung kailangan)**
Kung hihingin ang password:

1. Pumunta sa GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Click **"Generate new token"**
3. Piliin ang expiration at permissions (check ang `repo` permission)
4. Click **"Generate token"**
5. **Kopyahin agad ang token** (hindi mo na ito makikita ulit!)
6. Gamitin ang token bilang password sa Step 9

---

## ✅ VERIFICATION
Pagkatapos ng Step 9, i-refresh ang GitHub repository page. Dapat makikita mo na ang lahat ng files mo doon!

---

## 🔄 PARA SA MGA SUSUNOD NA UPDATE

Kapag may bagong changes ka:

```bash
# 1. I-check ang status
git status

# 2. I-add ang changes
git add .

# 3. I-commit
git commit -m "Description ng changes mo"

# 4. I-push sa GitHub
git push
```

---

## ⚠️ IMPORTANT NOTES

1. **Security**: Ang `config.php` ay hindi ma-upload dahil sa `.gitignore`. Gumawa ng `config.example.php` na template kung gusto mo.

2. **Database**: Ang `database.sql` ay hindi rin ma-upload. I-share ito sa ibang paraan kung kailangan.

3. **First Time Push**: Maaaring matagal ang unang push depende sa laki ng files.

---

## 🆘 TROUBLESHOOTING

**Error: "fatal: remote origin already exists"**
```bash
git remote remove origin
git remote add origin https://github.com/username/Menstrual.git
```

**Error: "Permission denied"**
- Siguraduhing tama ang username at password/token
- I-check kung may internet connection

**Error: "failed to push some refs"**
```bash
git pull origin main --allow-unrelated-histories
git push -u origin main
```

---

## 📞 NEED HELP?
Kung may problema, i-check ang:
- Git documentation: [git-scm.com/docs](https://git-scm.com/docs)
- GitHub Help: [docs.github.com](https://docs.github.com)

---

**Good luck! 🚀**

